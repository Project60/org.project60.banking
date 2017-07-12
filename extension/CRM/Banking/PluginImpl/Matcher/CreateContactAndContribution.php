<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
 * This matcher will offer to create a new contribution if all the required information is present
 */
class CRM_Banking_PluginImpl_Matcher_CreateContactAndContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->auto_exec))              $config->auto_exec = false;
    if (!isset($config->required_values))        $config->required_values = array("btx.financial_type_id");
    if (!isset($config->factor))                 $config->factor = 1.0;
    if (!isset($config->threshold))              $config->threshold = 0.0;
    if (!isset($config->source_label))           $config->source_label = ts('Source');
    if (!isset($config->lookup_contact_by_name)) $config->lookup_contact_by_name = array("hard_cap_probability" => 0.9);
  }


  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $penalty     = $this->getPenalty($btx);
    $data_parsed = $btx->getDataParsed();

    // first see if all the required values are there
    if (!$this->requiredValuesPresent($btx)) return null;

    // Force a fake flag that we've found a new contact id
    $contacts_found = array(
      0 => '0.90',
    );

    // finally generate suggestions
    foreach ($contacts_found as $contact_id => $contact_probability) {
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(ts("Create a new contact and assign contribution"));
      $suggestion->setId("create-$contact_id");
      $suggestion->setParameter('contact_id', $contact_id);

      // set probability manually, I think the automatic calculation provided by ->addEvidence might not be what we need here
      $contact_probability -= $penalty;
      if ($contact_probability >= $threshold) {
        $suggestion->setProbability($contact_probability);
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

    $cnt = $this->get_contact_data($btx, $match, $contact_id);
    $prefix = 'cm_';

    foreach ($cnt as $cnt_key => $cnt_value) {
      if(preg_match('/^cm_/', $cnt_key)) {
        if ($cnt_value != '') {
          if (substr($cnt_key, 0, strlen($prefix)) == $prefix) {
            $cnt_key = substr($cnt_key, strlen($prefix));
          }
          $contact[$cnt_key] = $cnt_value;
        }
      }
    }

    // create contact
    $query = $contact;
    $query['version'] = 3;

    // First check if we have address / email / phone to assign

    $contact_result = civicrm_api('Contact', 'create', $query);
    if (isset($contact_result['is_error']) && $contact_result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create contact.")."<br/>".ts("Error was: ").$contact_result['error_message'], ts('Error'), 'error');
      return true;
    }

    // Create address, if any
    if ($contact['street_address']) {
      $address_query['street_address'] = $contact['street_address'];
    }
    if ($contact['postal_code']) {
      $address_query['postal_code'] = $contact['postal_code'];
    }
    if ($contact['city']) {
      $address_query['city'] = $contact['city'];
    }
    if ($contact['country']) {
      $address_query['country'] = $contact['country'];
    }
    if ($contact['location_type_id']) {
      $address_query['location_type_id'] = $contact['location_type_id'];
    } else {
      $address_query['location_type_id'] = '3';
    }
    // Do we have enough data to create an address entry for that contact?
    if (count($address_query) > 0) {
      $address_query['contact_id'] = $contact_result['id'];
      $address_query['version'] = 3;
      $address_query['is_primary'] = '1';
      $address_result = civicrm_api('Address', 'create', $address_query);
      if (isset($address_result['is_error']) && $address_result['is_error']) {
        CRM_Core_Session::setStatus(ts("Couldn't create address.")."<br/>".ts("Error was: ").$address_result['error_message'], ts('Error'), 'error');
      }
    }

    // Create phone, if any
    if ($contact['phone']) {
      $phone_query['phone'] = $contact['phone'];
      $phone_query['location_type_id'] = '3';
      $phone_query['phone_type_id'] = '1';
    }

    // // Do we have enough data to create a phone entry for that contact?
    if (count($phone_query) > 0) {
      $phone_query['contact_id'] = $contact_result['id'];
      $phone_query['version'] = 3;
      $phone_query['is_primary'] = '1';
      $phone_result = civicrm_api('Phone', 'create', $phone_query);
      if (isset($phone_result['is_error']) && $phone_result['is_error']) {
        CRM_Core_Session::setStatus(ts("Couldn't create phone.")."<br/>".ts("Error was: ").$phone_result['error_message'], ts('Error'), 'error');
      }
    }

    // create contribution
    $query = $this->get_contribution_data($btx, $suggestion, $contact_result['id']);

    $query['version'] = 3;
    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create contribution.")."<br/>".ts("Error was: ").$result['error_message'], ts('Error'), 'error');
      return true;
    }

    $suggestion->setParameter('contribution_id', $result['id']);
    $suggestion->setParameter('contact_id', $contact_result['id']);

    // save the account
    $this->storeAccountWithContact($btx, $contact_result['id']);

    // wrap it up
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
    $smarty_vars = array();

    $cnt = $this->get_contact_data($btx, $match, $contact_id);
    $contact['display_name'] = $cnt['cm_display_name'];

    $contribution = $this->get_contribution_data($btx, $match, $contact_id);

    // look up financial type
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];

    // assign source
    $smarty_vars['source']       = CRM_Utils_Array::value('source', $contribution);
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // Assign Contact Data
    $smarty_vars['contact_display_name'] = $contact['display_name'];

    // assign to smarty and compile HTML
    $smarty_vars['contribution']  = $contribution;

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContact.suggestion.tpl');
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
    $smarty_vars['contribution_id'] = $match->getParameter('contribution_id');
    $smarty_vars['contact_id']      = $match->getParameter('contact_id');

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContact.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

  /**
   * compile the contribution data from the BTX and the propagated values
   */
  function get_contribution_data($btx, $match, $contact_id) {
    $contribution = array();
    $contribution['contact_id'] = $contact_id;
    $contribution['total_amount'] = $btx->amount;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge($contribution, $this->getPropagationSet($btx, $match, 'contribution'));
    return $contribution;
  }
  function get_contact_data($btx,$match,$contact_id) {
    $data_parsed = $btx->getDataParsed();
    $contact = array_merge($data_parsed, $this->getPropagationSet($btx, $match, 'contact'));
    return $contact;
  }
}

