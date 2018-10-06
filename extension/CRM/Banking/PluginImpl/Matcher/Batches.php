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
 * The Batch Matcher will try reconcile the payment with exported accounting batches that matches the given amount
 */
class CRM_Banking_PluginImpl_Matcher_Batches extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    $config = $this->_plugin_config;
    // read config, set defaults
    // payment should no arrive BEFORE the batch was exported:
    if (!isset($config->export_date_to_payment_min)) $config->export_date_to_payment_min = "-1 days";
    // payment should be there within a month:
    if (!isset($config->export_date_to_payment_max)) $config->export_date_to_payment_max = "+30 days";
    // paymeny is expected to arrive after three days:
    if (!isset($config->export_date_to_payment_delay)) $config->export_date_to_payment_delay = "+3 days";
    // ... but +/- 2 days is also fine without a penalty
    if (!isset($config->export_date_to_payment_tolerance)) $config->export_date_to_payment_tolerance = "2 days";
    // the sum must not deviate by more than 5%:
    if (!isset($config->total_amount_tolerance)) $config->total_amount_tolerance = 0.05;
    // ignore batches older than one year (for performance reasons):
    if (!isset($config->exclude_batches_older_than)) $config->exclude_batches_older_than = "1 YEAR";
  }


  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    // get list of existing batches (cache in context)
    $existing_batches = $context->getCachedEntry('banking.pluginimpl.matcher.batch');
    if ($existing_batches==NULL) {
      $existing_batches = $this->generateBatchList();
      $context->setCachedEntry('banking.pluginimpl.matcher.batch', $existing_batches);
    }


    // look for a matching batch
    $config = $this->_plugin_config;
    $booking_date = strtotime($btx->booking_date);
    $matching_batches = array();
    foreach ($existing_batches as $batch) {
      $total_amount = $batch['total'];
      if (!empty($batch['export_date'])) {
        $submission_date = strtotime($batch['export_date']);  
      } elseif ($batch['modified_date']) {
        $submission_date = strtotime($batch['modified_date']);  
      } else {
        $submission_date = strtotime($batch['created_date']);
      }

      // check amount
      if (abs(1-($total_amount/$btx->amount)) > $config->total_amount_tolerance) continue;

      // check export_date_to_payment_min / max
      if ($booking_date < strtotime($config->export_date_to_payment_min, $submission_date)) continue;
      if ($booking_date > strtotime($config->export_date_to_payment_max, $submission_date)) continue;

      // batch is accepted -> calculate probability:
      // first factor: expected income time
      $time_penalty_total = strtotime('-'.$config->export_date_to_payment_tolerance, abs($booking_date - $submission_date));
      $time_penalty = min(1.0, 1 - $time_penalty_total / (strtotime($config->export_date_to_payment_max)-strtotime($config->export_date_to_payment_min)));
      
      // second factor: equal amount
      $amount_penalty = 1.0 - (abs(1-($total_amount/$btx->amount)) / $config->total_amount_tolerance);

      // third factor: statmentes pending
      $status_penalty = 1.0 - ((count($this->getNonPendingContributionIDs($batch['id']))) / $batch['item_count']);

      $matching_batches[$batch['id']] = $time_penalty * $amount_penalty * $status_penalty;
    }

    // for each matched batch, create a suggestion
    foreach ($matching_batches as $batch_id => $batch_probability) {
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(E::ts("Settles a contribution batch"));
      $suggestion->setParameter('batch_id', $batch_id);
      $suggestion->setId("batch-".$batch_id);
      $suggestion->setProbability($batch_probability);
      $btx->addSuggestion($suggestion);
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
    // load the batch
    $batch_id = $suggestion->getParameter('batch_id');
    $result = civicrm_api('Batch', 'getsingle', array('version' => 3, 'id' => $batch_id));
    if ($result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(E::ts("Internal error! Cannot find batch %s"), $match->getParameter('batch_id')), E::ts('Error'), 'error');
    }

    if ($suggestion->getParameter('override_status') || !count($this->getNonPendingContributionIDs($batch_id))) {
      // all seems fine, lets set all these contributions to 'completed'
      $contribution_status_completed = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');

      // first, get all contributions:
      $contributionIDs = array();
      $query = 
        "SELECT contribution.id as contribution_id FROM civicrm_entity_batch AS batch ".
        "INNER JOIN civicrm_entity_financial_trxn  AS trxn2c       ON batch.entity_id=trxn2c.financial_trxn_id AND batch.entity_table='civicrm_financial_trxn' ".
        "INNER JOIN civicrm_contribution           AS contribution ON trxn2c.entity_id=contribution.id AND trxn2c.entity_table='civicrm_contribution' ".
        "WHERE batch.batch_id = $batch_id;";
      $result = CRM_Core_DAO::executeQuery($query);
      while ($result->fetch()) {
        array_push($contributionIDs, $result->contribution_id);
      }

      // now, set them all to completed:
      foreach ($contributionIDs as $contribution_id) {
        $result = civicrm_api('Contribution', 'create', array('version' => 3, 'id' => $contribution_id, 'contribution_status_id' => $contribution_status_completed, 'pay_later' => 0));
        if ($result['is_error']) {
          CRM_Core_Session::setStatus(sprintf(E::ts("Internal error! Cannot complete contribution %s. Error message was: '%s'"), $contribution_id, $result['error_message']), E::ts('Error'), 'error');
        }
      }

      // update the batch
      $batch_status_received = banking_helper_optionvalue_by_groupname_and_name('batch_status', 'Received');
      $update_batch_query = array('version' => 3, 'id' => $batch_id, 'modified_date' => date('YmdHis'), 'status_id' => $batch_status_received);
      $result = civicrm_api('Batch', 'create', $update_batch_query);
      if ($result['is_error']) {
        CRM_Core_Session::setStatus(sprintf(E::ts("Internal error! Cannot find batch %s"), $match->getParameter('batch_id')), E::ts('Error'), 'error');
      }

      // notify the user
      CRM_Core_Session::setStatus(sprintf(E::ts("Completed all %d contributions."), count($contributionIDs)), E::ts('Batch completed'), 'info');

      // complete by setting the status to 'processed'
      $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
      $btx->setStatus($newStatus);
      parent::execute($suggestion, $btx);
      return TRUE;

    } else {
      // this means, there ARE contributions in a non-pending state, AND the override was not requested:
      CRM_Core_Session::setStatus(sprintf(E::ts("Some contribtions in batch %s are not in state 'pending', and override was not enabled. The payment was NOT processed!"), $batch_id), E::ts('Error'), 'error');
    }
    return false;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    // record the override parameter
    $batch_id = $match->getParameter('batch_id');
    if ($parameters["batches_override_${batch_id}"]) {
      $match->setParameter('override_status', 1);      
    } else {
      $match->setParameter('override_status', 0);
    }
  }

    /** 
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    // load the batch
    $batch_id = $match->getParameter('batch_id');
    $result = civicrm_api('Batch', 'getsingle', array('version' => 3, 'id' => $batch_id));
    if ($result['is_error']) {
      return E::ts("Internal error! Cannot find batch #").$match->getParameter('batch_id');
    } else {
      // prepare the information
      $batch = $result;
      $batch_link = CRM_Utils_System::url("civicrm/batchtransaction", "reset=1&bid=$batch_id");
      $created_date = $batch['created_date'];
      $exported_date = $batch['exported_date']?$batch['exported_date']:E::ts("not exported");
      $nonPendingContributionIDs = $this->getNonPendingContributionIDs($batch_id);

      if (!empty($nonPendingContributionIDs)) {
        $text .= "<div>".E::ts("WARNING! Not all contributions of this batch are in status 'pending'.");
        $text .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input id=\"batches_override_$batch_id\" class=\"form-checkbox\" type=\"checkbox\" value=\"1\" name=\"batches_override_$batch_id\">";
        $text .= E::ts("Override and set all contributions to 'completed' anyway.")."</input></div>";
      } else {
        $text .= "<div>".E::ts("All contributions of this batch will be set to 'completed'.")."</div>";
      }

      // add contribution summary table
      $text .= "<br/><div><table border=\"1\"><tr>";
      $text .= "<td><div class=\"btxvalue\"><a href=\"$batch_link\" target=\"_blank\">".$batch['title']."</td>";
      $text .= "<td><div class=\"btxlabel\">".E::ts("Created").":&nbsp;</div><div class=\"btxvalue\">$created_date</td>";
      $text .= "<td><div class=\"btxlabel\">".E::ts("Exported").":&nbsp;</div><div class=\"btxvalue\">$exported_date</td>";
      $text .= "<td><div class=\"btxlabel\">".E::ts("Transactions").":&nbsp;</div><div class=\"btxvalue\">&nbsp;&nbsp;".$batch['item_count']."</td>";
      $text .= "<td><div class=\"btxlabel\">".E::ts("Amount").":&nbsp;</div><div class=\"btxvalue\">".$batch['total']."&nbsp;EUR</td>";
      $text .= "</tr></table></div>";

      return $text;
    }
  }


  /** 
   * Generate html code to visualize the executed match.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    $batch_id = $match->getParameter('batch_id');
    $batch_link = CRM_Utils_System::url("civicrm/batchtransaction", "reset=1&bid=$batch_id");
    return "<p>".sprintf(E::ts("This transaction was associated with <a href=\"%s\">payment batch #%s</a>."), $batch_link, $batch_id)."</p>";
  }


  /**
   * This will generate a list of all batches, that are within the configured limits
   * List entries will be [batch_id, title, description, created_date, modified_date, exported_date, status_id, type_id, mode_id, total, item_count, payment_instrument_id]
   */
  function generateBatchList() {
    $batch_list = array();
    $time_horizon = "(NOW() - INTERVAL ".$this->_plugin_config->exclude_batches_older_than.")";
    $query = "SELECT * FROM civicrm_batch 
                  WHERE created_date > $time_horizon
                     OR modified_date > $time_horizon
                     OR exported_date > $time_horizon;";
    $batch_result = CRM_Core_DAO::executeQuery($query);
    while ($batch_result->fetch()) {
      $batch = array(
        'id'                      => $batch_result->id,
        'title'                   => $batch_result->title,
        'description'             => $batch_result->description,
        'created_date'            => $batch_result->created_date,
        'modified_date'           => $batch_result->modified_date,
        'exported_date'           => $batch_result->exported_date,
        'status_id'               => $batch_result->status_id,
        'type_id'                 => $batch_result->type_id,
        'mode_id'                 => $batch_result->mode_id,
        'total'                   => $batch_result->total,
        'item_count'              => $batch_result->item_count,
        'payment_instrument_id'   => $batch_result->payment_instrument_id);
      array_push($batch_list, $batch);
    }
    return $batch_list;
  }

  /**
   * will get all IDs of the batch's contributions that are not in the state pending.
   */
  function getNonPendingContributionIDs($batch_id) {
    $nonPendingContributionIDs = array();
    $contribution_status_pending = banking_helper_optionvalue_by_groupname_and_name('contribution_status','Pending');
    $query = 
      "SELECT contribution.id as contribution_id FROM civicrm_entity_batch AS batch ".
      "INNER JOIN civicrm_entity_financial_trxn  AS trxn2c       ON batch.entity_id=trxn2c.financial_trxn_id AND batch.entity_table='civicrm_financial_trxn' ".
      "INNER JOIN civicrm_contribution           AS contribution ON trxn2c.entity_id=contribution.id AND trxn2c.entity_table='civicrm_contribution' ".
      "WHERE batch.batch_id = $batch_id 
         AND contribution.contribution_status_id != '$contribution_status_pending';";
    $result = CRM_Core_DAO::executeQuery($query);
    while ($result->fetch()) {
      array_push($nonPendingContributionIDs, $result->contribution_id);
    }
    return $nonPendingContributionIDs;
  }
}

