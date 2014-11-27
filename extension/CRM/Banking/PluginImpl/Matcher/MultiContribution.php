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
 * This matcher tries to reconcile the payments with existing contributions. 
 * There are two modes:
 *   default      - matches e.g. to pending contributions and changes the status to completed
 *   cancellation - matches negative amounts to completed contributions and changes the status to cancelled
 */
class CRM_Banking_PluginImpl_Matcher_MultiContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))              $config->threshold = 0.5;
    if (!isset($config->required_values))        $config->required_values = [];
    if (!isset($config->contribution_selector))  $config->contribution_selector = [];
    if (!isset($config->amount_penalty))         $config->amount_penalty = 0.5;
    if (!isset($config->value_propagation))      $config->value_propagation = [];
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold = $config->threshold;
    $data_parsed = $btx->getDataParsed();

    // check requirements
    foreach ($config->required_values as $required_key) {
      if ($this->getPropagationValue($btx, $required_key)==NULL) {
        // there is no value given for this key => bail
        return null;
      }
    }

    // Don't do anyhting if we don't have a single criteria
    if (empty($config->contribution_selector)) return NULL;

    // find and load the contributions
    $query = array(
      'version'       => 3,
      'option.limit'  => 999);
    foreach ($config->contribution_selector as $criteria) {
      $value = $criteria[1];
      if ( strrpos($value, '{')==0 && strrpos($value, '}')==strlen($value)-1 ) {
        // this is a token look up value
        $token = substr($value, 1,strlen($value)-2);
        if (isset($data_parsed[$token])) {
          $value = $data_parsed[$token];
        } else {
          error_log("Token {$token} not found.");
        }
      }
      $query[$criteria[0]] = $value;
    }

    // load the contributions and evaluate
    $results = civicrm_api('Contribution', 'get', $query);
    if (!empty($results['is_error'])) {
      error_log("Query failed, error was: " . $results['error_message']);
      return NULL;
    }

    // if there is no contributions, quit
    if (empty($results['values'])) {
      // TODO: do we want to allow this under certain circumstances?
      //   if so, add a config option
      return NULL;
    }

    // gather information
    $probability = 1.0;
    $contribution_ids = array();
    $total_amount = 0.0;
    $max_date = 0;
    $min_date = 9999999999;
    foreach ($results['values'] as $contribution) {
      $contribution_ids[] = $contribution['id'];
      $total_amount += $contribution['total_amount'];
      $receive_date = strtotime($contribution['receive_date']);
      if ($receive_date > $max_date) $max_date = $receive_date;
      if ($receive_date < $min_date) $min_date = $receive_date;
    }

    // evaluate the results
    $amount_delta = abs($total_amount - $btx->amount);
    if ($amount_delta != 0) $probability -= $config->amount_penalty;

    $time_range = $max_date - $min_date;
    // TODO: add a penalty for a difference in the receive date?

    $time_delta = abs(strtotime($btx->booking_date) - ($max_date+$min_date)/2.0);
    // TODO: add a penalty for a difference from the booking date?

    // create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setProbability($probability);
    $suggestion->setId("multi-" . implode('-', $contribution_ids));
    $suggestion->setParameter('contribution_ids', $contribution_ids);
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
    // load the contribution
    $contribution_ids = $suggestion->getParameter('contribution_ids');


    // TODO: check if the selection criteria still applies...


    // TODO: configure final contribution status?
    $final_contribution_status_id = 1; // completed

    // set all contributions to completed
    foreach ($contribution_ids as $contribution_id) {
      // TODO: error handling, etc.
      $query = array(
        'id'                      => $contribution_id,
        'contribution_status_id'  => $final_contribution_status_id);
      $query = array_merge($query, $this->getPropagationSet($btx, 'contribution'));   // add propagated values  
      civicrm_api('Contribution', 'create', $query);

      // TODO: do we want to store bank accounts here?
      //$this->storeAccountWithContact($btx, $result['values'][$contribution_id]['contact_id']);
    }

    // set the status of the transaction to complete
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return true;
  }

  /** 
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    // load the contribution
    $contribution_ids = $match->getParameter('contribution_ids');

    // set all contributions to completed
    $contributions = array();
    foreach ($contribution_ids as $contribution_id) {
      // TODO: error handling
      $contributions[] = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    }

    // gather information
    $text = "<div>".ts("The payment should be reconciled with the following contributions:")."</div>";

    // add contribution summary table
    $text .= "<br/><div><table border=\"1\"><tr>";
    $text .= "<td><div class=\"btxlabel\">".ts("Donor").":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">".ts("Amount").":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">".ts("Date").":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">".ts("Type").":&nbsp;</div></td>";
    $text .= "<td align='center'></td>";
    $text .= "</tr>";
    foreach ($contributions as $contribution) {
      $edit_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "reset=1&action=update&context=contribution&id={$contribution['id']}&cid={$contribution['contact_id']}");
      $contact_link = CRM_Utils_System::url("civicrm/contact/view", "&reset=1&cid={$contribution['contact_id']}");
      $text .= "<tr>";
      $text .= "<td><div class=\"btxvalue\"><a href=\"$contact_link\" target=\"_blank\">".$contribution['sort_name']."</td>";
      $text .= "<td><div class=\"btxvalue\">".$contribution['total_amount']." ".$contribution['currency']."</td>";
      $text .= "<td><div class=\"btxvalue\">".$contribution['receive_date']."</td>";
      $text .= "<td><div class=\"btxvalue\">".$contribution['financial_type']."</td>";
      $text .= "<td align='center'><a href=\"$edit_link\" target=\"_blank\">".ts("edit contribution")."</td>";
      $text .= "</tr>";
    }

    $text .= "</table></div>";
    return $text;
  }

  /** 
   * Generate html code to visualize the executed match.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    // TODO: make nicer...

    // load the contribution
    $contribution_ids = $match->getParameter('contribution_ids');

    $text = "<p>".sprintf(ts("This payment was associated with the following %d contributions:"), count($contribution_ids));
    foreach ($contribution_ids as $contribution) {
      $text .= '[' . $contribution['id'] . '],';
    }
    return $text;
  }
}

