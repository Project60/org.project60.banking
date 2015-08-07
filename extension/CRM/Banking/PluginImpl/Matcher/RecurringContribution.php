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
    if (!isset($config->search_wo_contacts))            $config->search_wo_contacts = FALSE;  // if true, the matcher will keep searching (using the search_terms) even if no contacts are found
    if (!isset($config->contact_id_list))               $config->contact_id_list = '';  // if not empty, take contacts from comma separated list instead of contact search
    if (!isset($config->created_contribution_status))   $config->created_contribution_status = 'Completed';
    if (!isset($config->recurring_contribution_status)) $config->recurring_contribution_status = array('Pending');
    if (!isset($config->suggestion_title))              $config->suggestion_title = '';
    if (!isset($config->recurring_mode))                $config->recurring_mode = 'static'; // see getExpectedDate()

    // amount check / amount penalty
    if (!isset($config->amount_check))                  $config->amount_check = "1";
    if (!isset($config->amount_relative_minimum))       $config->amount_relative_minimum = 1.0;
    if (!isset($config->amount_relative_maximum))       $config->amount_relative_maximum = 1.0;
    if (!isset($config->amount_absolute_minimum))       $config->amount_absolute_minimum = 0;
    if (!isset($config->amount_absolute_maximum))       $config->amount_absolute_maximum = 1;
    if (!isset($config->amount_penalty))                $config->amount_penalty = 1.0;
    if (!isset($config->currency_penalty))              $config->currency_penalty = 0.5;

    // date check / date range
    if (!isset($config->received_date_check))           $config->received_date_check = "1";  // WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW
    if (!isset($config->received_date_minimum))         $config->received_date_minimum = "-100 days";
    if (!isset($config->received_date_maximum))         $config->received_date_maximum = "+1 days";
    if (!isset($config->date_penalty))                  $config->date_penalty = 1.0;
    if (!isset($config->payment_instrument_penalty))    $config->payment_instrument_penalty = 0.0;
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

    // create suggestions
    if (!empty($config->search_terms)) {
      $query = $this->getPropagationSet($btx, $suggestion, '', $config->search_terms);
    } else {
      $query = array();
    }
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
        if ($penalty) {
          $suggestion->addEvidence($penalty, ts("A general penalty was applied."));
        }
        $suggestion->setProbability($probability);
        $btx->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    $config      = $this->_plugin_config;
    $smarty_vars = array();

    // load the recurring contribution
    $rcontribution_id = $suggestion->getParameter('recurring_contribution_id');
    $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
    $last_contribution = NULL;
    
    $due_date  = self::getExpectedDate($rcontribution, $btx, $config->recurring_mode, $last_contribution);
    if ($due_date) {
      $current_due_date = date('Y-m-d', $due_date);
    } else {
      $current_due_date = ts('None');
    }
    $recorded_due_date = $suggestion->getParameter('expected_date');
    if ($recorded_due_date != $current_due_date) {
      // something changed...
      CRM_Core_Session::setStatus(ts('The situation for the recurring contribution seems to have changed. Please analyse transaction again.'), ts('Recurring contributtion changed'), 'alert');
      return false;
    }

    // go ahead and create the contribution
    $contribution = array();
    $contribution['contact_id']                 = $suggestion->getParameter('contact_id');
    $contribution['total_amount']               = $btx->amount;
    $contribution['receive_date']               = $btx->booking_date;
    $contribution['currency']                   = $btx->currency;
    $contribution['financial_type_id']          = $rcontribution['financial_type_id'];
    $contribution['payment_instrument_id']      = $rcontribution['payment_instrument_id'];
    $contribution['campaign_id']                = CRM_Utils_Array::value('campaign_id', $rcontribution);
    $contribution['recurring_contribution_id']  = $rcontribution_id;
    $contribution['contribution_status_id']     = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $config->created_contribution_status);
    $contribution = array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));
    $contribution['version'] = 3;
    $result = civicrm_api('Contribution', 'create', $contribution);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create contribution.")."<br/>".ts("Error was: ").$result['error_message'], ts('Error'), 'error');
      return false;
    } 

    // success!
    $suggestion->setParameter('contribution_id', $result['id']);

    // save the account
    $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
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
    $config      = $this->_plugin_config;
    $smarty_vars = array();

    // load the recurring contribution
    $rcontribution_id = $match->getParameter('recurring_contribution_id');
    $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));

    // load the recurring contribution
    $contact_id = $match->getParameter('contact_id');
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));

    // get due date:
    $last_contribution = NULL;
    $due_date = self::getExpectedDate($rcontribution, $btx, $config->recurring_mode, $last_contribution);

    // assign to smarty and compile HTML
    $smarty_vars['recurring_contribution'] = $rcontribution;
    $smarty_vars['last_contribution']      = $last_contribution;
    $smarty_vars['contact']                = $contact;
    $smarty_vars['due_date']               = $due_date?date('Ymdhis', $due_date):'';
    $smarty_vars['expected_date']          = $match->getParameter('expected_date');
    $smarty_vars['expected_amount']        = $match->getParameter('expected_amount');
    $smarty_vars['penalties']              = $match->getEvidence();

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
    $smarty_vars['rcontribution_id'] = $match->getParameter('recurring_contribution_id');
    $smarty_vars['contribution_id']  = $match->getParameter('contribution_id');
    $smarty_vars['contact_id']       = $match->getParameter('contact_id');

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/RecurringContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }




  /*************************************************************
   *                    HELPER FUNCTIONS                      ** 
   *************************************************************/


  /**
   * create suggestions for matching recurring contributions
   */
  function createRecurringContributionSuggestions($query, $probability, $btx, $context) {
    $config      = $this->_plugin_config;
    $data_parsed = $btx->getDataParsed();
    $suggestions = array();

    $rcur_result = civicrm_api3('ContributionRecur', 'get', $query);
    foreach ($rcur_result['values'] as $rcur_id => $rcur) {
      // find the next expected date for the recurring contribution
      $expected_date = self::getExpectedDate($rcur, $btx, $config->recurring_mode);
      if ($expected_date==NULL) continue;

      // create a suggestion
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setId("recurring-$rcur_id");
      $suggestion->setParameter('recurring_contribution_id', $rcur_id);
      $suggestion->setParameter('contact_id', $rcur['contact_id']);
      $suggestion->setParameter('expected_date', date('Y-m-d', $expected_date));
      $suggestion->setParameter('expected_amount', $rcur['amount']);
      if (!empty($config->suggestion_title)) {
        $suggestion->setTitle($config->suggestion_title);
      } 
      if ($probability<1.0) {
        $suggestion->addEvidence(1.0-$probability, ts("The contact could not be uniquely identified."));        
      }

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
          if ($penalty) {
            $suggestion->addEvidence($penalty, ts("The amount of the transaction differs from the expected amount."));
            $probability -= $penalty;            
          }
        }
      }

      // CHECK CURRENCY
      if ($context->btx->currency != $rcur['currency']) {
        $suggestion->addEvidence($config->currency_penalty, ts("The currency of the transaction is not as expected."));
        $probability -= $config->currency_penalty;
      }

      // CHECK EXPECTED DATE
      if ($config->received_date_check) {
        // use date only
        $transaction_date = strtotime(date('Y-m-d', strtotime($context->btx->value_date)));

        if ($expected_date < strtotime($config->received_date_minimum, $transaction_date)) continue;
        if ($expected_date > strtotime($config->received_date_maximum, $transaction_date)) continue;
        
        // calculate the date penalties
        $date_delta = abs($expected_date - $transaction_date);
        $date_range = max(1, strtotime($config->received_date_maximum) - strtotime($config->received_date_minimum));

        if ($date_range) {
          $penalty = $config->date_penalty * ($date_delta / $date_range);
          if ($penalty) {
            $suggestion->addEvidence($penalty, ts("The date of the transaction deviates from the expeted date."));
            $probability -= $penalty;            
          }
        }
      }

      $suggestion->setProbability($probability);
      $suggestions[] = $suggestion;
    }

    return $suggestions;
  }


  /**
   * Try to find the next expected date for the given
   * recurring contribution. The behaviour depends on the
   * $config->recurring_mode setting:
   *  'static': expects the contribution with a fixed cycle, i.e. start with
   *            the first cycle day after start_date, and the on that day 
   *            for each cycle 
   *  'adapt':  expects the contribution one cycle after the last recorded payment.
   *            that means, that if one payment is late, the next payment is 
   *            expected on the next cycle day one cycle after the last one
   *            (or the start date in case of the first payment)
   *  'float':  expects the contribution exactly one cycle after the last recorded payment.
   *            that means, that the cycle day is ignored
   *  'total':  expects the next payment on the date according to the static calculation
   *            and the total of the existing payments.
   *            Example 1: monthly recurring contribution over 10€, but donor paid 30€ in 
   *            the first installment => next expected date is only 3 months later
   *            Example 2: same recurring contribution as example 1, but donor paid 10€, then
   *            nothing, than another 10€ => next expected date is NOT 1 month after the last, but
   *            two months after start_date, since there's not enough money.
   *            if the calculated due date is before the current transaction date, it will return the
   *            transactions's date, so that any payment will be accepted, even if too late.
   *  (more to come...?)
   */
  public static function getExpectedDate($rcontribution, $btx, $recurring_mode, &$last_contribution = NULL) {
    // find a maximum
    $max_date = strtotime("+1 year");
    if (!empty($rcontribution['end_date']) && strtotime($rcontribution['end_date'])<$max_date) {
      $max_date = strtotime($rcontribution['end_date']);
    }
    if (!empty($rcontribution['cancel_date']) && strtotime($rcontribution['cancel_date'])<$max_date) {
      $max_date = strtotime($rcontribution['cancel_date']);
    }

    // these modes require the list of recurring contributions
    $contribution_query = civicrm_api3('Contribution', 'get', array(
      'contribution_recur_id' => $rcontribution['id'],
      'options'               => array('limit' => 9999),
      ));

    // find the last contribution
    $contributions = $contribution_query['values'];
    $total_amount      = 0.0;
    $last_contribution = NULL;
    foreach ($contributions as $contribution_id => $contribution) {
      if ($contribution['contribution_status_id'] == 1) {
        $total_amount += $contribution['total_amount'];
      }
      if (empty($contribution['receive_date'])) continue;
      if ($last_contribution==NULL) {
        $last_contribution = $contribution;
        continue;
      }
      if (strtotime($last_contribution['receive_date']) < strtotime($contribution['receive_date'])) {
        $last_contribution = $contribution;
      }
    }

    // get some values
    $cycle_day   = $rcontribution['cycle_day'];
    $interval    = $rcontribution['frequency_interval'];
    $unit        = $rcontribution['frequency_unit'];
    $target_date = strtotime($btx->booking_date);

    
    if ($recurring_mode == 'static') {
      $start_date = strtotime($rcontribution['start_date']);
      $next_date =  mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));
      
      $closest_date = $next_date;
      while ( $next_date <= $max_date) {
        if (abs($next_date-$target_date) <= abs($closest_date-$target_date)) {
          $closest_date = $next_date;
          $next_date = strtotime("+$interval $unit", $next_date);
        } else {
          // once we're not improving any more, we can stop
          return $closest_date;
        }
      }


    } elseif ($recurring_mode == 'adapt') {
      if ($last_contribution==NULL) {
        // this relies on the last contribution
        return self::getExpectedDate($rcontribution, $btx, 'static');
      }
      $last = strtotime($last_contribution['receive_date']);
      $last_month = strtotime("-1 month", $last);
      $cycle_day_after  = mktime(0, 0, 0, date('n', $last) + (date('j', $last) > $cycle_day), $cycle_day, date('Y', $last));
      $cycle_day_before = mktime(0, 0, 0, date('n', $last_month) + (date('j', $last_month) > $cycle_day), $cycle_day, date('Y', $last_month));
      if (abs($last-$cycle_day_before) < abs($last-$cycle_day_after)) {
        $last_cycle_date = $cycle_day_before;
      } else {
        $last_cycle_date = $cycle_day_after;
      }
      $next = strtotime("+$interval $unit", $last_cycle_date);
      if ($next < $max_date) {
        return $next;
      }


    } elseif ($recurring_mode == 'float') {
      if ($last_contribution==NULL) {
        // this relies on the last contribution
        return self::getExpectedDate($rcontribution, $btx, 'static');
      }
      $last = strtotime($last_contribution['receive_date']);
      $next = strtotime("+$interval $unit", $last);
      if ($next < $max_date) {
        return $next;
      }


    } elseif ($recurring_mode == 'total') {
      $cycle_count = (int) ($total_amount / $rcontribution['amount']);
      $unit_count  = $cycle_count * $interval;
      $start_date  = strtotime($rcontribution['start_date']);
      $start_date  = mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));

      $due_date   = strtotime("+$unit_count $unit", $start_date);
      if ($due_date < $target_date) {
        $due_date = $target_date;
      }
      return $due_date;
    }

    return NULL;
  }
}
