<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

use CRM_Banking_ExtensionUtil as E;

require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher will offer to create a new contribution if all the required information is present
 */
class CRM_Banking_PluginImpl_Matcher_CreateContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->auto_exec))              $config->auto_exec = false;
    if (!isset($config->required_values))        $config->required_values = array("btx.financial_type_id", "btx.campaign_id");
    if (!isset($config->factor))                 $config->factor = 1.0;
    if (!isset($config->threshold))              $config->threshold = 0.0;
    if (!isset($config->source_label))           $config->source_label = E::ts('Source');
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

    // then look up potential contacts
    $contacts_found = $context->findContacts($threshold, $data_parsed['name'], $config->lookup_contact_by_name);

    // finally generate suggestions
    foreach ($contacts_found as $contact_id => $contact_probability) {
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(E::ts("Create a new contribution"));
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
   * Execute the previously generated suggestion,
   *   and close the transaction
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   *   the suggestion to be executed
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   */
  public function execute($suggestion, $btx) {
    // create contribution
    $query = $this->get_contribution_data($btx, $suggestion, $suggestion->getParameter('contact_id'));
    $query['version'] = 3;
    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.")."<br/>".E::ts("Error was: ").$result['error_message'], E::ts('Error'), 'error');
      return true;
    }

    $suggestion->setParameter('contribution_id', $result['id']);

    // save the account
    $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));

    // link the contribution
    CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $result['id']);

    // wrap it up
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return TRUE;
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

    $contact_id   = $match->getParameter('contact_id');
    $contribution = $this->get_contribution_data($btx, $match, $contact_id);

    // load contact
    $contact = civicrm_api('Contact', 'getsingle', array('id' => $contact_id, 'version' => 3));
    if (!empty($contact['is_error'])) {
      $smarty_vars['error'] = $contact['error_message'];
    }

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

    // assign source
    $smarty_vars['source']       = CRM_Utils_Array::value('source', $contribution);
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // assign to smarty and compile HTML
    $smarty_vars['contact']       = $contact;
    $smarty_vars['contribution']  = $contribution;

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContribution.suggestion.tpl');
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
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateContribution.execution.tpl');
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
}

