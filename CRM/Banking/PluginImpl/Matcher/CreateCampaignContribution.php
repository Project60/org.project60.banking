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

declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

/**
 * This matcher will (offer to) create a new contribution based on a
 *   campaign activity in the contact's recent history, e.g. a mailing
 */
class CRM_Banking_PluginImpl_Matcher_CreateCampaignContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  public function __construct($config_name) {
  // phpcs:enable
    parent::__construct($config_name);

    // read config, set defaults (SHOULD OVERRIDE IN CONFIG)
    $config = $this->_plugin_config;
    if (!isset($config->auto_exec)) {
      $config->auto_exec = FALSE;
    }
    if (!isset($config->required_values)) {
      $config->required_values = ['btx.financial_type_id'];
    }
    if (!isset($config->threshold)) {
      $config->threshold = 0.5;
    }
    if (!isset($config->source_label)) {
      $config->source_label = E::ts('Source');
    }
    if (!isset($config->lookup_contact_by_name)) {
      $config->lookup_contact_by_name = ['hard_cap_probability' => 0.9];
    }

    // activity search profile (SHOULD OVERRIDE IN CONFIG)
    // activity campaign. If empty will use the campaign of the activity
    if (!isset($config->campaign_id)) {
      $config->campaign_id = NULL;
    }
    // activity type IDs to consider - or *any* if empty
    if (!isset($config->activity_type_id)) {
      $config->activity_type_id = NULL;
    }
    // activity status IDs to consider - or *any* if empty
    if (!isset($config->status_id)) {
      $config->status_id = [2];
    }
    // maximum time between the activity and the bank transaction
    if (!isset($config->time_frame)) {
      $config->time_frame = '40 days';
    }

    // penalty for finding active recurring contributions
    if (!isset($config->active_recurring_contribution_penalty)) {
      $config->active_recurring_contribution_penalty = 0.00;
    }
    // penalty for finding active recurring contributions
    if (!isset($config->activity_with_no_campaign_penalty)) {
      // ...default for which is: only activities with campaigns
      $config->activity_with_no_campaign_penalty = 1.00;
    }
    // default status is 'in Progress'
    if (!isset($config->active_recurring_contribution_status_ids)) {
      $config->active_recurring_contribution_status_ids = ['In Progress'];
    }

    // contribution create parameters (SHOULD OVERRIDE IN CONFIG)
    // optional, time AFTER the activity timestamp - or *any* if empty
    if (!isset($config->financial_type_id)) {
      $config->financial_type_id = 1;
    }
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array match structures
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
  // phpcs:enable
    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $penalty     = $this->getPenalty($btx);
    $activity_with_no_campaign_penalty = (double) $config->activity_with_no_campaign_penalty;
    $data_parsed = $btx->getDataParsed();

    if ($penalty) {
      $this->logMessage("Calculated general penalty for this match is: {$penalty}.", 'debug');
    }

    // get the potential contacts
    $contacts_found = $context->findContacts(
      $threshold,
      $data_parsed['name'] ?? E::ts('n/a'),
      $config->lookup_contact_by_name
    );
    $contact_ids_considered = array_keys($contacts_found);
    if (empty($contact_ids_considered)) {
      $this->logMessage('No eligible contacts found.', 'debug');
      return [];
    }

    // filter for valid activity status IDs
    // default
    $status_ids = ['Completed'];
    if (!empty($config->status_id)) {
      $status_ids = is_array($config->status_id) ? $config->status_id : [$config->status_id];
    }

    // generate an api query to look for eligible activities
    $time_frame = $config->time_frame ?? '30 days';
    $min_date = date('Y-m-d H:i:s', strtotime("{$btx->booking_date} - {$time_frame}"));
    // we add 24h to cover the whole day
    $max_date = date('Y-m-d H:i:s', strtotime("{$btx->booking_date} + 1 day"));
    $activity_search_query = [
      'option.limit'       => 0,
      'target_contact_id'  => ['IN' => $contact_ids_considered],
      'status_id'          => ['IN' => $status_ids],
      'activity_date_time' => ['BETWEEN' => [$min_date, $max_date]],
      'return' => [
        'target_contact_id',
        'activity_type_id',
        'subject',
        'campaign_id',
        'activity_date_time',
        'status_id',
      ],
    ];

    // add campaign IDs from the configuration
    if (!empty($config->campaign_id)) {
      if (!is_array($config->campaign_id)) {
        $config->campaign_id = explode(',', (string) $config->campaign_id);
      }
      $activity_search_query['campaign_id'] = ['IN' => $config->campaign_id];
    }

    // add activity type
    if (empty($config->activity_type_id)) {
      // add warning if no activity_type_id is given
      $this->logMessage('No activity_type_id configured, you would probably want to restrict the search to certain activity types!', 'debug');
    }
    else {
      if (!is_array($config->activity_type_id)) {
        $config->activity_type_id = explode(',', (string) $config->activity_type_id);
      }
      $activity_search_query['activity_type_id'] = ['IN' => $config->activity_type_id];
    }

    // add specific return values
    if (!empty($config->load_activity_fields)) {
      if (!is_array($config->load_activity_fields)) {
        $config->load_activity_fields = explode(',', (string) $config->load_activity_fields);
      }
      // add the values for display
      $config->load_activity_fields[] = 'campaign_id';
      $activity_search_query['return'] = implode(',', $config->load_activity_fields);
    }

    // run query
    $this->logMessage('Looking for activities with query: ' . json_encode($activity_search_query), 'debug');
    $this->logger->setTimer('campaign_contribution:search');
    try {
      $activities = civicrm_api3('Activity', 'get', $activity_search_query);
      $this->logMessage('Result is ' . json_encode($activities), 'debug');
      $this->logTime("Finding {$activities['count']} activities to consider", 'campaign_contribution:search');
    }
    catch (Exception $ex) {
      $this->logMessage('Failed to search for eligible activities, error was ' . $ex->getMessage(), 'error');
      return [];
    }

    // investigate and rate the activities found
    $activity_count_with_confidence_100 = 0;
    $suggestions = [];
    foreach ($activities['values'] as $activity) {
      $activity_id = $activity['id'];
      $contact_ids = array_values($activity['target_contact_id'] ?? []);
      foreach ($contact_ids as $contact_id) {
        if (isset($contacts_found[$contact_id])) {
          $no_campaign_penalty_applied = 0.0;
          $activity_confidence = max($contacts_found[$contact_id], 0.0);
          $this->logMessage("Found [{$contact_id}] with confidence {$activity_confidence} (including penalty of {$penalty})", 'debug');
          $multiple_recurring_contributions_penalty_applied = $this->adjustRatingOfRecurringContributions($contact_id, $activity_confidence, $btx);

          // also: add a penalty for activities without campaign (if configured this way)
          if ($activity_with_no_campaign_penalty > 0.0) {
            if (empty($activity['campaign_id'])) {
              $activity_confidence = min($activity_confidence - $activity_with_no_campaign_penalty, 0.99);
              $this->logMessage("Added a penalty of {$activity_with_no_campaign_penalty} because the activity [{$activity_id}] has no campaign.", 'debug');
              $no_campaign_penalty_applied = $activity_with_no_campaign_penalty;
            }
          }

          // apply the general penalty
          $activity_confidence = min($activity_confidence - $penalty, 1.0);

          if ($activity_confidence >= $threshold) {
            // this is one of the contacts we're looking for -> create suggestion
            $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
            $suggestion->setTitle(E::ts('Create Campaign Contribution'));
            $suggestion->setId("create-campaign-{$activity_id}-{$contact_id}");
            $suggestion->setParameter('contact_id', $contact_id);
            $suggestion->setParameter('campaign_id', $activity['campaign_id'] ?? NULL);
            $suggestion->setParameter('activity_id', $activity_id);
            $suggestion->setParameter('multiple_recurring_contributions_penalty_applied', $multiple_recurring_contributions_penalty_applied);
            $suggestion->setParameter('no_campaign_penalty_applied', $no_campaign_penalty_applied);
            $suggestion->setParameter('time_after_activity', strtotime("{$btx->booking_date}") - strtotime($activity['activity_date_time']));
            $suggestion->setProbability($activity_confidence);
            if ($activity_confidence == 1.0) {
              $activity_count_with_confidence_100++;
            }
            $this->logMessage('Added suggestion.', 'debug');
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

        // first adjust confidence based on temporal proximity
        $confidence = (float) $suggestion->getProbability();
        $this->logMessage('confidence: ' . $confidence, 'debug');
        $this->logMessage('time_after_activity: ' . $suggestion->getParameter('time_after_activity'), 'debug');
        $this->logMessage('time_window_size: ' . $time_window_size, 'debug');
        $adjusted_confidence = $confidence - ((float) $suggestion->getParameter('time_after_activity') / (float) $time_window_size);
        $adjusted_confidence = min($adjusted_confidence, 0.99);

        // don't create 100% matches at this point
        $suggestion->setProbability($adjusted_confidence);
        $this->logMessage("Adjusted confidence for suggestion from {$confidence} to {$adjusted_confidence}.", 'debug');
        $btx->addSuggestion($suggestion);
      }
    }
    else {
      // no more than one 100% suggestions from our end, so everything can go ahead without changes
      foreach ($suggestions as $suggestion) {
        $btx->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? NULL : $this->_suggestions;
  }

  protected function get_contribution_data($btx, $suggestion, $contact_id) {
    $contribution = [];
    $contribution['currency'] = $btx->currency;
    $contribution['financial_type_id'] = $this->getConfig()->financial_type_id ?? NULL;
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
      $this->logMessage('Trying to create contribution: ' . json_encode($contribution), 'debug');
      $contribution = civicrm_api3('Contribution', 'create', $contribution);
      $this->logMessage("Created contribution [{$contribution['id']}].", 'debug');
      $suggestion->setParameter('contribution_id', $contribution['id']);
      $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));
      CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution['id']);

    }
    catch (Exception $ex) {
      $this->logMessage('Error on contribution creation: ' . $ex->getMessage(), 'error');
      CRM_Core_Session::setStatus(
        E::ts('Error was: %1', [1 => $ex->getMessage()]),
        E::ts("Couldn't create contribution.") . '<br/>');
      return TRUE;
    }

    // wrap it up
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return TRUE;
  }

  /**
   * This will reduce the probability of a campaign contribution if
   *   and active recurring contribution is present
   *
   * @param int $contact_id
   *    contact ID
   *
   * @param float $contact_probability
   *    the probability of the contact to be adjusted (reduced)
   *
   * @param \CRM_Banking_BAO_BankTransaction $btx
   *
   * @return float penalty added to the contact's rating
   */
  public function adjustRatingOfRecurringContributions($contact_id, &$contact_probability, $btx) {
    // only look into this if there's a penalty and a contribution status set
    $recurring_contribution_penalty = (float) $this->_plugin_config->active_recurring_contribution_penalty ?? 0;
    if ($recurring_contribution_penalty <= 0) {
      // no penalty disables the check
      return 0.0;
    }
    $status_ids = (array) $this->_plugin_config->active_recurring_contribution_status_ids;
    if (empty($status_ids)) {
      // no status_id disables the check
      return 0.0;
    }

    // run the query
    $recurring_contribution_query = [
      'contact_id' => $contact_id,
      'contribution_status_id' => ['IN' => $status_ids],
      'start_date' => ['<=' => date('YmdHis', strtotime($btx->booking_date))],
      'return' => ['id'],
    ];
    $this->logMessage('Looking for recurring contributions with query: ' . json_encode($recurring_contribution_query), 'debug');
    $result = civicrm_api3('ContributionRecur', 'get', $recurring_contribution_query);

    // if there is, apply the penalty
    if ($result['count'] > 0) {
      $contact_probability = max(0.0, $contact_probability - $recurring_contribution_penalty);
      $this->logMessage("{$result['count']} active recurring contributions have been found with contact [{$contact_id}], suggestion will be reduced by a penalty of {$recurring_contribution_penalty}.", 'info');
      return $recurring_contribution_penalty;
    }
    else {
      $this->logMessage("No active recurring contributions have been found with contact [{$contact_id}], suggestion will not be penalised.", 'debug');
      return 0.0;
    }
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
  public function visualize_match(CRM_Banking_Matcher_Suggestion $match, $btx) {
    $smarty_vars = [];

    $contact_id      = $match->getParameter('contact_id');
    $activity_id     = $match->getParameter('activity_id');
    $no_campaign_penalty_applied = $match->getParameter('no_campaign_penalty_applied');
    $multiple_recurring_contributions_penalty_applied
      = $match->getParameter('multiple_recurring_contributions_penalty_applied');
    $contribution = $this->get_contribution_data($btx, $match, $contact_id);

    // load contact
    $contact = civicrm_api('Contact', 'getsingle', ['id' => $contact_id, 'version' => 3]);
    if (!empty($contact['is_error'])) {
      $smarty_vars['error'] = $contact['error_message'];
    }

    // load activity
    $activity = civicrm_api3('Activity', 'getsingle', ['id' => $activity_id]);

    // load campaign
    // this should always be the case, but better be sure
    if (!empty($activity['campaign_id'])) {
      $campaign = civicrm_api3('Campaign', 'getsingle', ['id' => $activity['campaign_id']]);
    }
    else {
      $campaign = ['title' => E::ts('-no campaign-')];
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
    if ($days_since_contribution == 0) {
      $smarty_vars['activity_title'] = E::ts("'%1' from the same day", [1 => $activity['subject']]);
    }
    else {
      $smarty_vars['activity_title'] = E::ts("'%1' from %2 days earlier", [1 => $activity['subject'], 2 => $days_since_contribution]);
    }
    $smarty_vars['activity_id']     = $activity['id'] ?? 'n/a';
    $smarty_vars['activity_url']    = CRM_Utils_System::url('civicrm/activity/view', "action=view&reset=1&id={$activity['id']}");
    $smarty_vars['activity_link']   = E::ts('<a class="crm-popup" href="%1">%2</a>', [1 => $smarty_vars['activity_url'], 2 => $smarty_vars['activity_title']]);

    // penalties
    $smarty_vars['no_campaign_penalty_applied'] = (int) (100.0 * $no_campaign_penalty_applied);
    $smarty_vars['multiple_recurring_contributions_penalty_applied']
      = (int) (100.0 * $multiple_recurring_contributions_penalty_applied);

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
  public function visualize_execution_info(CRM_Banking_Matcher_Suggestion $match, $btx) {
    // just assign to smarty and compile HTML
    $smarty_vars = [];
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
