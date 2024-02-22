<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2023 SYSTOPIA                            |
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
 * This matcher will (offer to) create a new contribution based on a
 *   campaign activity in the contact's recent history, e.g. a mailing
 */
class CRM_Banking_PluginImpl_Matcher_CreateCampaignContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults (SHOULD OVERRIDE IN CONFIG)
    $config = $this->_plugin_config;
    if (!isset($config->auto_exec))              $config->auto_exec = false;
    if (!isset($config->required_values))        $config->required_values = array("btx.financial_type_id");
    if (!isset($config->threshold))              $config->threshold = 0.9;
    if (!isset($config->source_label))           $config->source_label = E::ts('Source');
    if (!isset($config->lookup_contact_by_name)) $config->lookup_contact_by_name = array("hard_cap_probability" => 0.9);

    // activity search profile (SHOULD OVERRIDE IN CONFIG)
    if (!isset($config->campaign_id))       $config->campaign_id = null;         // activity campaign. If empty will use the campaign of the activity
    if (!isset($config->activity_type_id))  $config->activity_type_id = null;    // activity type IDs to consider - or *any* if empty
    if (!isset($config->status_id))         $config->status_id = [2];            // activity status IDs to consider - or *any* if empty
    if (!isset($config->time_frame))        $config->time_frame = "40 days";     // maximum time between the activity and the bank transaction

    // contribution create parameters (SHOULD OVERRIDE IN CONFIG)
    if (!isset($config->financial_type_id))  $config->financial_type_id = 1;     // optional, time AFTER the activity timestamp - or *any* if empty
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

    // get the potential contacts
    $contacts_found = $context->findContacts(
      $threshold,
      $data_parsed['name'] ?? E::ts("n/a"),
      $config->lookup_contact_by_name
    );
    $contact_ids_considered = array_keys($contacts_found);

    // filter for valid activity status IDs
    $status_ids = ['Completed']; // default
    if (!empty($config->status_id)) {
      $status_ids = is_array($config->status_id) ? $config->status_id : [$config->status_id];
    }

    // generate an api query to look for eligible activities
    $time_frame = $config->time_frame ?? '30 days';
    $min_date = date('Y-m-d H:i:s', strtotime("{$btx->booking_date} - {$time_frame}"));
    $max_date = date('Y-m-d H:i:s', strtotime("{$btx->booking_date} + 1 day")); // we add 24h to cover the whole day
    $activity_search_query = [
      'option.limit'       => 0,
      'target_contact_id'  => ['IN' => $contact_ids_considered],
      'status_id'          => ['IN' => $status_ids],
      'activity_date_time' => ['BETWEEN' => [$min_date, $max_date]],
      'return' => [
        'target_contact_id',
        'activity_type_id',
        'subject',
        'activity_date_time',
        'status_id',
      ]
    ];

    // add campaign IDs from the configuration
    if (!empty($config->campaign_id)) {
      if (!is_array($config->campaign_id)) {
        $config->campaign_id = explode(',', $config->campaign_id);
      }
      $activity_search_query['campaign_id'] = ['IN' => $config->campaign_id];
    }

    // add activity type
    if (empty($config->activity_type_id)) {
      // add warning if no activity_type_id is given
      $this->logMessage("No activity_type_id configured, you would probably want to restrict the search to certain activity types!", 'debug');
    } else {
      if (!is_array($config->activity_type_id)) {
        $config->activity_type_id = explode(',', $config->activity_type_id);
      }
      $activity_search_query['activity_type_id'] = ['IN' => $config->activity_type_id];
    }

    // add specific return values
    if (!empty($config->load_activity_fields)) {
      if (!is_array($config->load_activity_fields)) {
        $config->load_activity_fields = explode(',', $config->load_activity_fields);
      }
      // add the values for display
      $config->load_activity_fields[] = 'campaign_id';
      $activity_search_query['return'] = implode(',', $config->load_activity_fields);
    }

    // run query
    $this->logMessage("Looking for activities with query: " . json_encode($activity_search_query), 'debug');
    $this->logger->setTimer('campaign_contribution:search');
    try {
      $activities = civicrm_api3('Activity', 'get', $activity_search_query);
      $this->logMessage("Result is " . json_encode($activities), 'debug');
      $this->logTime("Finding {$activities['count']} activities to consider", 'campaign_contribution:search');
    } catch (Exception $ex) {
      $this->logMessage("Failed to search for eligible activities, error was " . $ex->getMessage(), 'error');
      return;
    }

    // investigate and rate the activities found
    $activity_count_with_confidence_100 = 0;
    $suggestions = [];
    foreach ($activities['values'] as $activity) {
      $activity_id = $activity['id'];
      $contact_ids = array_values($activity['target_contact_id']) ?? [];
      foreach ($contact_ids as $contact_id) {
        if (isset($contacts_found[$contact_id])) {
          $contact_probability = $contacts_found[$contact_id];
          if ($contact_probability >= $threshold) {
            // this is one of the contacts we're looking for -> create suggestion
            $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
            $suggestion->setTitle(E::ts("Create Campaign Contribution"));
            $suggestion->setId("create-campaign-{$activity_id}-{$contact_id}");
            $suggestion->setParameter('contact_id', $contact_id);
            $suggestion->setParameter('campaign_id', $activity['campaign_id']);
            $suggestion->setParameter('activity_id', $activity_id);
            $suggestion->setParameter('time_after_activity', strtotime("{$btx->booking_date}") - strtotime($activity['activity_date_time']));
            $suggestion->setProbability($contact_probability);
            if ($contact_probability == 1.0) $activity_count_with_confidence_100++;
            $this->logMessage("Added suggestion.", 'debug');
            $suggestions[] = $suggestion;
          }
        }
      }
    }

    // if there's more than one suggestion with 100% confidence, reduce
    if ($activity_count_with_confidence_100 > 1) {
      $this->logMessage("{$activity_count_with_confidence_100} suggestions with 100% confidence generated, will apply temporal distance penalties.", 'debug');
      $time_window_size = strtotime($max_date) - strtotime($min_date);
      foreach ($suggestions as $suggestion) {
        /** @var $suggestion CRM_Banking_Matcher_Suggestion */
        $confidence = (float) $suggestion->getProbability();
        $this->logMessage('confidence: ' . $confidence, 'debug');
        $this->logMessage('time_after_activity: ' . $suggestion->getParameter('time_after_activity'), 'debug');
        $this->logMessage('time_window_size: ' . $time_window_size, 'debug');
        $adjusted_confidence = $confidence - ((float) $suggestion->getParameter('time_after_activity') / (float) $time_window_size);
        $adjusted_confidence = min($adjusted_confidence, 0.99);
        $suggestion->setProbability($adjusted_confidence); // don't create 100% matches at this point
        $this->logMessage("Adjusted confidence for suggestion from {$confidence} to {$adjusted_confidence}.", 'debug');
        $btx->addSuggestion($suggestion);
      }
    } else {
      // no more than one 100% suggestions from our end, so everything can go ahead without changes
      foreach ($suggestions as $suggestion) {
        $btx->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  protected function get_contribution_data($btx, $suggestion, $contact_id)
  {
    $contribution = [];
    $contribution['currency'] = $btx->currency;
    $contribution['financial_type_id'] = $this->getConfig()->financial_type_id ?? null;
    $contribution['contact_id'] = $contact_id;
    $contribution['campaign_id'] = $suggestion->getParameter('campaign_id');
    $contribution['total_amount'] = $btx->amount;
    $contribution['receive_date'] = $btx->value_date;
    return array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));
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
    // gather contribution data
    $contribution = $this->get_contribution_data($btx, $suggestion, $suggestion->getParameter('contact_id'));
    try {
      $this->logMessage("Trying to create contribution: " . json_encode($contribution), 'debug');
      $contribution = civicrm_api3('Contribution', 'create', $contribution);
      $this->logMessage("Created contribution [{$contribution['id']}].", 'debug');
      $suggestion->setParameter('contribution_id', $contribution['id']);
      $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));
      CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution['id']);

    } catch (Exception $ex) {
      $this->logMessage("Error on contribution creation: " . $ex->getMessage(), 'error');
      CRM_Core_Session::setStatus(
        E::ts("Error was: %1", [1 => $ex->getMessage()]),
        E::ts("Couldn't create contribution.")."<br/>");
      return true;
    }

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
    $activity_id  = $match->getParameter('activity_id');
    $contribution = $this->get_contribution_data($btx, $match, $contact_id);

    // load contact
    $contact = civicrm_api('Contact', 'getsingle', array('id' => $contact_id, 'version' => 3));
    if (!empty($contact['is_error'])) {
      $smarty_vars['error'] = $contact['error_message'];
    }

    // load activity
    $activity = civicrm_api3('Activity', 'getsingle', ['id' => $activity_id]);

    // load campaign
    if ($activity['campaign_id']) {  // this should always be the case, but better be sure
      $campaign = civicrm_api3('Campaign', 'getsingle', ['id' => $activity['campaign_id']]);
    } else {
      // this shouldn't happen
      $campaign = ['title' => E::ts("-no campaign-")];
    }
    $smarty_vars['campaign'] = $campaign;

    // look up financial type
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];

    // assign source
    $smarty_vars['source']       = CRM_Utils_Array::value('source', $contribution);
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // assign to smarty and compile HTML
    $smarty_vars['activity']      = $contact;
    $smarty_vars['contribution']  = $contribution;

    // add activity info
    $days_since_contribution = (strtotime($btx->booking_date) - strtotime($activity['activity_date_time'])) / 60 / 60 / 24;
    $smarty_vars['activity_title'] = E::ts("'%1' from %2 days earlier", [1 => $activity['subject'], 2 => $days_since_contribution]);
    $smarty_vars['activity_id']    = $activity['id'] ?? 'n/a';
    $smarty_vars['activity_url']   = CRM_Utils_System::url('civicrm/activity/view', "action=view&reset=1&id={$activity['id']}");
    $smarty_vars['activity_link']  = E::ts('<a class="crm-popup" href="%1">%2</a>', [1 => $smarty_vars['activity_url'], 2 => $smarty_vars['activity_title']]);

    // add campaign info
    $smarty_vars['campaign_name']       = $campaign['title'];
    $smarty_vars['campaign_id']         = $campaign['id'];
    $smarty_vars['campaign_url']        = 'TODO';

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateCampaignContribution.suggestion.tpl');
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
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/CreateCampaignContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }
}

