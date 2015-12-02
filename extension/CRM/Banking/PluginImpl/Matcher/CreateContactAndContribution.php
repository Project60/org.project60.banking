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
    if (!isset($config->create_new_threshold))   $config->create_new_threshold = 1.0;
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

    // then look up potential contacts
    $contacts_found = $context->findContacts($threshold, $data_parsed['name'], $config->lookup_contact_by_name);

    // Bail if there is one (or more) contacts sufficiently well matched
    foreach ($contacts_found as $contact_id => $contact_probability) {
      if ($contact_probability >= $config->create_new_threshold) return null;
    }

    // Create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setTitle(ts("Create a new contact with a contribution"));
    $suggestion->setId("create_contact");
    $suggestion->setProbability(1.0 - $penalty);
    $btx->addSuggestion($suggestion);

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
    // create contribution
    $contact_data = $this->getPropagationSet($btx, $match, 'contact');

    // create the contact
    $query = $contact_data;
    $query['version'] = 3;
    // TODO: API Chaining for adding more data
    $contact_result = civicrm_api('Contact', 'create', $query);
    if (isset($contact_result['is_error']) && $contact_result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create contact.")."<br/>".ts("Error was: ").$result['error_message'], ts('Error'), 'error');
      return true;
    }     

    $contact_id = $contact_result['id'];
    $suggestion->setParameter('contact_id', $contact_id);
    $contribution_data = $this->get_contribution_data($btx, $suggestion, $contact_id);
    $contribution_data['contact_id'] = $contact_id;

    // Now lets create the contribution
    $query = $contribution_data;
    $query['version'] = 3;
    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create contribution.")."<br/>".ts("Error was: ").$result['error_message'], ts('Error'), 'error');
      return true;
    } 

    // TODO: create contribution
    $suggestion->setParameter('contribution_id', $result['id']);


    // save the account
    $this->storeAccountWithContact($btx, $contact_id);

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

    $contact_data      = $this->getPropagationSet($btx, $match, 'contact');
    $sc_contact_data   = $this->getPropagationSet($btx, $match, 'softcredit_contact');
    $contribution_data = $this->get_contribution_data($btx, $suggestion, $contact_id);

    // look up financial type
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];

    // look up campaign
    if (!empty($contribution['campaign_id'])) {
      $campaign = civicrm_api('Campaign', 'getsingle', array('id' => $contribution['campaign_id'], 'version' => 3));
      if (!empty($contact['is_error'])) {
        $smarty_vars['error'] = $campaign['error_message'];
      } else {
        $smarty_vars['campaign'] = $campaign;
      }
    }
    
    $smarty_vars['contact']      = $contact_data;
    $smarty_vars['contribution'] = $contribution_data;
    
    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContactAndContribution.suggestion.tpl');
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
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContactAndContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

  /**
   * compile the contribution data from the BTX and the propagated values
   */
  function get_contribution_data($btx, $match, $contact_id) {
    $contribution = array();
    $contribution['total_amount'] = $btx->amount;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge($contribution, $this->getPropagationSet($btx, $match, 'contribution'));
    return $contribution;
  }

}

