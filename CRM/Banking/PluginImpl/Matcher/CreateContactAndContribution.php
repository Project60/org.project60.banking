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

declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

/**
 * This matcher will offer to create a new contribution if all the required information is present
 */
class CRM_Banking_PluginImpl_Matcher_CreateContactAndContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   *
   * @param string $config_name
   */
  public function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->auto_exec)) {
      $config->auto_exec = FALSE;
    }
    if (!isset($config->required_values)) {
      $config->required_values = ['btx.financial_type_id', 'btx.campaign_id'];
    }
    if (!isset($config->factor)) {
      $config->factor = 1.0;
    }
    if (!isset($config->threshold)) {
      $config->threshold = 0.0;
    }
    if (!isset($config->source_label)) {
      $config->source_label = E::ts('Source');
    }
    if (!isset($config->lookup_contact_by_name)) {
      $config->lookup_contact_by_name = ['hard_cap_probability' => 0.9];
    }
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array<CRM_Banking_Matcher_Suggestion>
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $penalty     = $this->getPenalty($btx);
    $data_parsed = $btx->getDataParsed();

    // first see if all the required values are there
    if (!$this->requiredValuesPresent($btx)) {
      return [];
    }

    // and if we have a financial_type_id
    if (!isset($data_parsed['financial_type_id'])) {
      return [];
    }

    // and if we have a payment_instrument_id
    if (!isset($data_parsed['payment_instrument_id']) and !isset($data_parsed['payment_instrument'])) {
      return [];
    }

    // and if we have a name
    if (!isset($data_parsed['name']) or ($data_parsed['name'] === '') or (count(explode(' ', $data_parsed['name'])) < 2)) {
      return [];
    }

    // finally generate suggestions
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setTitle(E::ts('Create new contact and new contribution'));
    $suggestion->setId('create-contact-and-contribution');
    $suggestion->setProbability(1);
    $btx->addSuggestion($suggestion);

    // that's it...
    return (count($this->_suggestions) > 0) ? NULL : $this->_suggestions;
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
    // create contact
    $contact = $this->get_contact_data($btx, $suggestion);
    $query = $this->get_query($contact);
    $result = civicrm_api4('Contact', 'create', $query);
    if (isset($result['is_error']) && ($result['is_error'] === 1) && is_string($result['error_message'])) {
      CRM_Core_Session::setStatus(E::ts("Couldn't create contact.") . '<br/>' . E::ts('Error was: ') . $result['error_message'], E::ts('Error'), 'error');
      return TRUE;
    }
    if (is_array($result[0]) and is_int($result[0]['id'])) {
      $contact_id = $result[0]['id'];
    }
    else {
      if (is_string($result['error_message'])) {
        CRM_Core_Session::setStatus(E::ts("Couldn't create contact.") . '<br/>' . E::ts('Error was: ') . $result['error_message'], E::ts('Error'), 'error');
      }
      return TRUE;
    }

    // create contribution
    $contribution = $this->get_contribution_data($btx, $suggestion);
    $contribution['contact_id'] = $contact_id;
    $query = $this->get_query($contribution);
    $result = civicrm_api4('Contribution', 'create', $query);
    if (isset($result['is_error']) && ($result['is_error'] === 1) && is_string($result['error_message'])) {
      CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.") . '<br/>' . E::ts('Error was: ') . $result['error_message'], E::ts('Error'), 'error');
      return TRUE;
    }
    if (is_array($result[0]) and is_int($result[0]['id'])) {
      $contribution_id = $result[0]['id'];
    }
    else {
      if (is_string($result['error_message'])) {
        CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.") . '<br/>' . E::ts('Error was: ') . $result['error_message'], E::ts('Error'), 'error');
      }
      return TRUE;
    }

    $suggestion->setParameter('contribution_id', $contribution_id);
    $suggestion->setParameter('contact_id', $contact_id);

    // save the account
    $this->storeAccountWithContact($btx, $contact_id);

    // link the contribution
    CRM_Banking_BAO_BankTransactionContribution::linkContribution(intval($btx->id), intval($contribution_id));

    // wrap it up
    $newStatus = CRM_Banking_Helpers_OptionValue::banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return TRUE;
  }

  /**
   * Generate html code to visualize the given match. The visualization may also
   *  provide interactive form elements.
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   *   suggestion data as previously generated by this plugin instance
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction the match refers to
   *
   * @return string code snippet
   */
  public function visualize_match(CRM_Banking_Matcher_Suggestion $suggestion, $btx) {
    $smarty_vars = [];

    $contact = $this->get_contact_data($btx, $suggestion);

    $contribution = [];
    $contribution['total_amount'] = $btx->amount;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));

    $contribution = $this->get_contribution_data($btx, $suggestion);

    // look up financial type
    $financial_types = CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes();
    $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];

    // look up campaign
    if (isset($contribution['campaign_id']) and (strval($contribution['campaign_id']) !== '') and CRM_Core_Component::isEnabled('CiviCampaign')) {
      $campaigns = civicrm_api4('Campaign', 'get', ['where' => [['id', '=', $contribution['campaign_id']]]]);
      $smarty_vars['campaign'] = $campaigns[0];
    }

    // assign source
    $smarty_vars['source']       = $contribution['source'] ?? NULL;
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // assign to smarty and compile HTML
    $smarty_vars['contact']       = $contact;
    $smarty_vars['contribution']  = $contribution;

    if ($contact['contact_type'] === 'Organization') {
      $smarty_vars['is_organization'] = TRUE;
    }

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
   * @param CRM_Banking_Matcher_Suggestion $match
   *   match data as previously generated by this plugin instance
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction the match refers to
   *
   * @return string html code snippet
   */
  public function visualize_execution_info(CRM_Banking_Matcher_Suggestion $match, $btx) {
    // just assign to smarty and compile HTML
    $smarty_vars = [];
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
   * Build a query to use with CiviCRM APIv4.
   *
   * @param array<mixed> $values
   *   Associative array, for example
   *   [
   *     'first_name' => 'Florence',
   *     'last_name' => 'Nightingale',
   *   ]
   * @return array<string,array<mixed>|boolean>
   *   Associative array, which you can use with CiviCRM APIv4
   */
  private function get_query($values) {
    $query = [
      'values' => $values,
      'checkPermissions' => TRUE,
    ];
    return $query;
  }

  /**
   * Compile the contact data from the BTX and the propagated values.
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   *   the suggestion to be visualized or executed
   * @return array<string>
   *   Associative array, for example
   *   [
   *     'contact_type' => 'Individual',
   *     'first_name' => 'Florence',
   *     'last_name' => 'Nightingale',
   *   ]
   */
  private function get_contribution_data($btx, $suggestion) {
    $contribution = [];
    $contribution['total_amount'] = $btx->amount;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));
    return $contribution;
  }

  /**
   * Compile the contact data from the BTX and the propagated values.
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   *   the suggestion to be visualized or executed
   * @return array<string>
   *   Associative array, for example
   *   [
   *     'contact_type' => 'Individual',
   *     'first_name' => 'Florence',
   *     'last_name' => 'Nightingale',
   *   ]
   */
  private function get_contact_data($btx, $suggestion) {
    $data_parsed = $btx->getDataParsed();
    $full_name = $data_parsed['name'];
    $contact_type = $this->get_contact_type($full_name);
    $contact = $this->get_contact_name_parts($full_name, $contact_type);
    $contact = array_merge($contact, $this->getPropagationSet($btx, $suggestion, 'contact'));
    return $contact;
  }

  /**
   * Analyse a full name and determine its parts like the first name.
   *
   * @param string $full_name
   *   The full name. (Ex: 'Florence Nightingale')
   * @param string $contact_type
   *   Either 'Individual' or 'Household' or 'Organization'
   * @return array<string,string|null>
   *   Associative array, for example
   *   [
   *     'contact_type' => 'Individual',
   *     'first_name' => 'Florence',
   *     'last_name' => 'Nightingale',
   *   ]
   */
  private function get_contact_name_parts($full_name, $contact_type) {
    // extract formal title from $full_name
    $regex_list = $this->getFormalTitles();
    $formal_title = NULL;
    foreach ($regex_list as $regex) {
      preg_match($regex, strtolower($full_name), $matches, PREG_OFFSET_CAPTURE);
      if (count($matches) > 0) {
        $offset = $matches[0][1];
        $title_length = strlen($matches[0][0]);
        $name_before_title = trim(substr($full_name, 0, $offset));
        $formal_title = substr($full_name, $offset, $title_length);
        $name_after_title = trim(substr($full_name, $offset + $title_length));
        $full_name = trim($name_before_title . ' ' . $name_after_title);
        break;
      }
    }

    // build contact data
    $contact = [];
    $contact['contact_type'] = $contact_type;
    if ($formal_title === NULL) {
      $contact['formal_title'] = $formal_title;
    }

    if ($contact_type === 'Organization') {
      $contact['organization_name'] = $full_name;
    }
    else {
      // contact_type == 'Individual'
      // TODO: contact_type == 'Household'
      $words = explode(' ', $full_name);

      if (count($words) === 2) {
        if (str_contains($full_name, ',')) {
          $first_name = str_replace(',', '', $words[1]);
          $last_name = str_replace(',', '', $words[0]);
        }
        else {
          $first_name = $words[0];
          $last_name = $words[1];
        }
      }
      else {
        // three or more words
        $first_name = $words[0] . ' ' . $words[1];
        $last_name = '';
        foreach ($words as $key => $value) {
          if ($key > 1) {
            $last_name = $last_name . ' ' . $value;
          }
        }
      }
      $contact['first_name'] = $first_name;
      $contact['last_name'] = $last_name;
    }

    return $contact;
  }

  /**
   * Analyse a full name and determine the contact type from it.
   *
   * @param string $full_name
   *   The full name. (Ex: 'CiviCRM LLC')
   * @return string
   *   either 'Organization' or 'Individual'
   */
  private function get_contact_type($full_name) {
    $regex_list = $this->getOrganizationRegex();

    foreach ($regex_list as $regex) {
      preg_match($regex, strtolower($full_name), $matches);
      if (count($matches) > 0) {
        return 'Organization';
      }
    }

    // if we reach this code here, it's not an organization
    // TODO: check for household
    return 'Individual';
  }

  /**
   * Get the regular expressions to identify organizations from plugin config
   *
   * @return array<string>
   */
  public function getOrganizationRegex() {
    if (isset($this->_plugin_config->organization_regex)) {
      $regex_list = $this->_plugin_config->organization_regex;
      return $regex_list;
    }
    else {
      return [];
    }
  }

  /**
   * Get the regular expressions to find formal titles from plugin config
   *
   * @return array<string>
   */
  public function getFormalTitles() {
    if (isset($this->_plugin_config->formal_titles)) {
      $regex_list = $this->_plugin_config->formal_titles;
      return $regex_list;
    }
    else {
      return [];
    }
  }

}
