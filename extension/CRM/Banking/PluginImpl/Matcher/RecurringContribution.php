<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2015 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher tries to reconcile the payments with existing memberships. 
 */
class CRM_Banking_PluginImpl_Matcher_RecurringContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))                     $config->threshold = 0.5;
    if (!isset($config->search_terms))                  $config->search_terms = array();
    if (!isset($config->contact_id_list))               $config->contact_id_list = '';  // if not empty, take contacts from comma separated list instead of contact search
    if (!isset($config->search_wo_contacts))            $config->search_wo_contacts = FALSE;  // if true, the matcher will keep searching (using the search_terms) even if no contacts are found
    if (!isset($config->recurring_contribution_status)) $config->recurring_contribution_status = array('Pending');
    if (!isset($config->suggestion_title))              $config->suggestion_title = ts("Installment of Recurring Contribution");

    // amount check / amount penalty
    if (!isset($config->amount_check))            $config->amount_check = "1";
    if (!isset($config->amount_relative_minimum)) $config->amount_relative_minimum = 1.0;
    if (!isset($config->amount_relative_maximum)) $config->amount_relative_maximum = 1.0;
    if (!isset($config->amount_absolute_minimum)) $config->amount_absolute_minimum = 0;
    if (!isset($config->amount_absolute_maximum)) $config->amount_absolute_maximum = 1;
    if (!isset($config->amount_penalty))          $config->amount_penalty = 1.0;
    if (!isset($config->currency_penalty))        $config->currency_penalty = 0.5;

    // date check / date range
    if (!isset($config->received_date_check))        $config->received_date_check = "1";  // WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW
    if (!isset($config->received_range_days))        $config->received_range_days = 366;  // WARNING: INCREASING THIS COULD MAKE THE PROCESS VERY SLOW    
    if (!isset($config->received_date_minimum))      $config->received_date_minimum = "-100 days";
    if (!isset($config->received_date_maximum))      $config->received_date_maximum = "+1 days";
    if (!isset($config->date_penalty))               $config->date_penalty = 1.0;
    if (!isset($config->payment_instrument_penalty)) $config->payment_instrument_penalty = 0.0;
  }

  /** 
   * Generate a set of suggestions for the given bank transaction
   * 
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config      = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $data_parsed = $btx->getDataParsed();
    $penalty     = $this->getPenalty();

    // find potential contacts
    $contactID2probability = array();
    if (empty($config->contact_id_list)) {
      $nameSearch = $context->findContacts($threshold, $data_parsed['name'], $config->lookup_contact_by_name);
      foreach ($nameSearch as $contact_id => $probability) {
        if (($probability - $penalty) < $threshold) continue;
        $contactID2probability[$contact_id] = $probability;
      }
    } else {
      if (!empty($data_parsed[$config->contact_id_list])) {
        $id_list = explode(',', $data_parsed[$config->contact_id_list]);
        foreach ($id_list as $contact_id) {
          $contact_id = (int) $contact_id;
          if ($contact_id > 0) {
            $contactID2probability[$contact_id] = 1.0;
          }
        }
      }
    }
    error_log('CONTACTS:'.print_r($contactID2probability,1));

    // create suggestions
    $query = $this->getPropagationSet($btx, $suggestion, '', $config->search_terms);
    if ($config->search_wo_contacts) {
      $suggestions = $this->createRecurringContributionSuggestions($query, 1.0, $btx, $context);
    } else {
      $suggestions = array();
      foreach ($contactID2probability as $contact_id => $probability) {
        $query['contact_id'] = $contact_id;
        $new_suggestions = $this->createRecurringContributionSuggestions($query, $probability, $btx, $context);
        $suggestions = array_merge($suggestions, $new_suggestions);
      }
    }

    // apply penalties and threshold
    foreach ($suggestions as $suggestion) {
      $probability = $suggestion->getProbability();
      $probability -= $penalty;
      if ($probability >= $threshold) {
        $suggestion->addEvidence($penalty, ts("A general penalty was applied."));
        $suggestion->setProbability($probability);
        $btx->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  /**
   * create suggestions for matching recurring contributions
   */
  function createRecurringContributionSuggestions($query, $probability, $btx, $context) {
    error_log(print_r($query,1));
    $config      = $this->_plugin_config;
    $data_parsed = $btx->getDataParsed();
    $suggestions = array();

    $rcur_result = civicrm_api3('ContributionRecur', 'get', $query);
    foreach ($rcur_result['values'] as $rcur_id => $rcur) {
      error_log('RCUR ' . print_r($rcur,1));
      // create a suggestion
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setId("recurring-$rcur_id");
      $suggestion->setParameter('recurring_contribution_id', $rcur_id);
      $suggestion->setParameter('contact_id', $rcur['contact_id']);

      // CHECK AMOUNT
      if ($config->amount_check) {
        // calculate the amount penalties (equivalent to CRM_Banking_PluginImpl_Matcher_ExistingContribution)
        $transaction_amount = $btx->amount;
        $expected_amount = $rcur['amount'];

        $amount_delta = $transaction_amount - $expected_amount;
        if (   ($transaction_amount < ($expected_amount * $config->amount_relative_minimum))
            && ($amount_delta < $config->amount_absolute_minimum)) continue;
        if (   ($transaction_amount > ($expected_amount * $config->amount_relative_maximum))
            && ($amount_delta > $config->amount_absolute_maximum)) continue;      

        $amount_range_rel = $transaction_amount * ($config->amount_relative_maximum - $config->amount_relative_minimum);
        $amount_range_abs = $config->amount_absolute_maximum - $config->amount_absolute_minimum;
        $amount_range = max($amount_range_rel, $amount_range_abs);

        if ($amount_range) {
          $penalty = $config->amount_penalty * (abs($amount_delta) / $amount_range);
          $suggestion->addEvidence($penalty, ts("The amount of the transaction differs from the expected amount."));
          $probability -= $penalty;
        }
      }

      // CHECK CURRENCY
      if ($context->btx->currency != $rcur['currency']) {
        $suggestion->addEvidence($config->currency_penalty, ts("The currency of the transaction is not as expected."));
        $probability -= $config->currency_penalty;
      }

      // CHECK EXPECTED DATE
      if ($config->received_date_check) {
        $expected_date = $this->getNextExpectedDate($rcur);
        $transaction_date = strtotime($context->btx->value_date);

        if ($expected_date < strtotime($config->received_date_minimum, $transaction_date)) continue;
        if ($expected_date > strtotime($config->received_date_maximum, $transaction_date)) continue;
        
        // calculate the date penalties
        $date_delta = abs($expected_date - $transaction_date);
        $date_range = max(1, strtotime($config->received_date_maximum) - strtotime($config->received_date_minimum));

        if ($date_range) {
          $penalty = $config->date_penalty * ($date_delta / $date_range);
          $suggestion->addEvidence($penalty, ts("The date of the transaction deviates from the expeted date."));
          $probability -= $penalty;
        }
      }

      $suggestion->setProbability($probability);
      $suggestions[] = $suggestion;
    }

    return $suggestions;
  }













  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    $membership_id = $suggestion->getParameter('membership_id');
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));
    $membership_type = civicrm_api3('MembershipType', 'getsingle', array('id' => $membership['membership_type_id']));


    // TODO: verify validity of suggestion (is outdated?)

    // // 1. create contribution
    // $contribution_parameters = array(
    //     'contact_id'        => $membership['contact_id'],
    //     'total_amount'      => $btx->amount,
    //     'currency'          => $btx->currency,
    //     'receive_date'      => $btx->value_date,
    //     'financial_type_id' => $this->getMembershipOption($membership_type['id'], 'financial_type_id', $membership_type['financial_type_id']),
    //     'version'           => 3,
    //   );
    // $contribution_parameters = array_merge($contribution_parameters, $this->getPropagationSet($btx, $suggestion, 'contribution'));
    // $contribution = civicrm_api('Contribution', 'create',  $contribution_parameters);
    // if (!empty($contribution['is_error'])) {
    //   CRM_Core_Session::setStatus(ts("Couldn't create contribution.")."<br/>".ts("Error was: ").$contribution['error_message'], ts('Error'), 'error');
    //   return true;      
    // }

    // // 2. connect to membership
    // civicrm_api3('MembershipPayment', 'create',  array(
    //   'membership_id'   => $membership_id,
    //   'contribution_id' => $contribution['id']
    //   ));

    // // wrap it up
    // $suggestion->setParameter('contact_id',      $membership['contact_id']);
    // $suggestion->setParameter('contribution_id', $contribution['id']);
    // $this->storeAccountWithContact($btx, $membership['contact_id']);

    // $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    // $btx->setStatus($newStatus);
    // parent::execute($suggestion, $btx);
    return true;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    // NOTHING to do...
  }

  /** 
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    $smarty_vars = array();

    // // load the contribution
    // $membership_id = $match->getParameter('membership_id');
    // $last_fee_id   = $match->getParameter('last_fee_id');

    // // LOAD entities
    // // TODO: error handling
    // $membership        = civicrm_api('Membership', 'getsingle', array('id' => $membership_id, 'version'=>3));
    // $membership_type   = civicrm_api('MembershipType', 'getsingle', array('id' => $membership['membership_type_id'], 'version'=>3));
    // $membership_status = civicrm_api('MembershipStatus', 'getsingle', array('id' => $membership['status_id'], 'version'=>3));
    // $contact           = civicrm_api('Contact', 'getsingle', array('id' => $membership['contact_id'], 'version'=>3));
    // $last_fee          = civicrm_api('Contribution', 'getsingle', array('id' => $last_fee_id, 'version'=>3));

    // // calculate some stuff
    // $last_fee['days']   = round((strtotime($btx->booking_date)-(int) strtotime($last_fee['receive_date'])) / (60 * 60 * 24));
    // $membership['days'] = round((strtotime($btx->booking_date)-strtotime($membership['start_date'])) / (60 * 60 * 24));
    // $membership['percentage_of_minimum'] = round(($btx->amount / (float) $membership_type['minimum_fee']) * 100);
    // $membership['title'] = $this->getMembershipOption($membership['membership_type_id'], 'title', $membership_type['name']);

    // // assign to smarty and compile HTML
    // $smarty_vars['membership']        = $membership;
    // $smarty_vars['membership_type']   = $membership_type;
    // $smarty_vars['membership_status'] = $membership_status;
    // $smarty_vars['contact']           = $contact;
    // $smarty_vars['last_fee']          = $last_fee;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/RecurringContribution.suggestion.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

  /** 
   * Generate html code to visualize the executed match.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    // just assign to smarty and compile HTML
    $smarty_vars = array();
    $smarty_vars['recurring_contribution_id']    = $match->getParameter('recurring_contribution_id');
    $smarty_vars['contact_id']                   = $match->getParameter('contact_id');

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/RecurringContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

}

