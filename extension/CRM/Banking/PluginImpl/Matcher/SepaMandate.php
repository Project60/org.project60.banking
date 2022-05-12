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
require_once 'packages/eval-math/evalmath.class.php';

/**
 * This matcher tries to reconcile the payments with existing contributions.
 * There are two modes:
 *   default      - matches e.g. to pending contributions and changes the status to completed
 *   cancellation - matches negative amounts to completed contributions and changes the status to cancelled
 *
 * The right contributions are identified based on data_parsed['sepa_mandate'] and data_parsed['sepa_batch'].
 * if data_parsed['sepa_batch'] is missing, it will try to identify the correct contribution based on the date.
 */
class CRM_Banking_PluginImpl_Matcher_SepaMandate extends CRM_Banking_PluginModel_Matcher {

  protected $contribution = NULL;

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold)) $config->threshold = 0.5;
    if (!isset($config->received_date_minimum)) $config->received_date_minimum = "-10 days";
    if (!isset($config->received_date_maximum)) $config->received_date_maximum = "+10 days";
    if (!isset($config->deviation_penalty)) $config->deviation_penalty = 0.1;
    if (!isset($config->value_propagation)) $config->value_propagation = array();

    if (!isset($config->cancellation_enabled)) $config->cancellation_enabled = FALSE;
    if (!isset($config->cancelled_contribution_status_id)) $config->cancelled_contribution_status_id = NULL; // default is cancelled
    if (!isset($config->cancellation_general_penalty)) $config->cancellation_general_penalty = 0.0;
    if (!isset($config->cancellation_update_mandate_status_OOFF)) $config->cancellation_update_mandate_status_OOFF = 'INVALID';
    if (!isset($config->cancellation_update_mandate_status_RCUR)) $config->cancellation_update_mandate_status_RCUR = false;
    if (!isset($config->cancellation_default_reason)) $config->cancellation_default_reason = E::ts("Unspecified SEPA cancellation");
    if (!isset($config->cancellation_date_minimum)) $config->cancellation_date_minimum = "-10 days";
    if (!isset($config->cancellation_date_maximum)) $config->cancellation_date_maximum = "+30 days";
    if (!isset($config->cancellation_status_penalty)) $config->cancellation_status_penalty = array("1" => 0.0, "5" => 0.2); // is a mapping of "contribution status id" => "penalty". If status not in list, no suggestion will be generated
    if (!isset($config->cancellation_amount_relative_minimum)) $config->cancellation_amount_relative_minimum = 1.0;
    if (!isset($config->cancellation_amount_relative_maximum)) $config->cancellation_amount_relative_maximum = 1.0;
    if (!isset($config->cancellation_amount_absolute_minimum)) $config->cancellation_amount_absolute_minimum = 1.0;
    if (!isset($config->cancellation_amount_absolute_maximum)) $config->cancellation_amount_absolute_maximum = 1.0;
    if (!isset($config->cancellation_amount_penalty)) $config->cancellation_amount_penalty = $config->deviation_penalty;
    if (!isset($config->cancellation_penalty_threshold)) $config->cancellation_penalty_threshold = $config->deviation_penalty;
    if (!isset($config->cancellation_value_propagation)) $config->cancellation_value_propagation = $config->value_propagation;

    // extended cancellation features: enter cancel_reason
    if (!isset($config->cancellation_cancel_reason))         $config->cancellation_cancel_reason         = 0; // set to 1 to enable
    if (!isset($config->cancellation_cancel_reason_edit))    $config->cancellation_cancel_reason_edit    = 1; // set to 0 to disable user input
    if (!isset($config->cancellation_cancel_reason_source))  $config->cancellation_cancel_reason_source  = 'cancel_reason';
    if (!isset($config->cancellation_cancel_reason_default)) $config->cancellation_cancel_reason_default = E::ts('Unknown');

    // extended cancellation features: fee
    if (!isset($config->cancellation_cancel_fee))            $config->cancellation_cancel_fee            = 0; // set to 1 to enable
    if (!isset($config->cancellation_cancel_fee_edit))       $config->cancellation_cancel_fee_edit       = 1; // set to 0 to disable user input
    if (!isset($config->cancellation_cancel_fee_source))     $config->cancellation_cancel_fee_source     = 'cancellation_fee'; // external source field in btx->data_parsed
    if (!isset($config->cancellation_cancel_fee_store))      $config->cancellation_cancel_fee_store      = 'match.cancel_fee'; // where to store the calculated fee, for syntax see value_propagation
    if (!isset($config->cancellation_cancel_fee_default))    $config->cancellation_cancel_fee_default    = 'difference';  // evaluated term, valid variables: 'difference'- (btx->amount + contribution->total_amount), 'source'- content of btx->data_parsed[$config->cancellation_cancel_fee_source]
    // add to value_propagation
    if ($config->cancellation_cancel_fee && !empty($config->cancellation_cancel_fee_store)) {
      // add entry to value propagation
      if (!isset($config->cancellation_value_propagation)) $config->cancellation_value_propagation = array();
      $config->cancellation_value_propagation->{'match.cancel_fee'} = $config->cancellation_cancel_fee_store;
    }

    // create activity
    if (!isset($config->cancellation_create_activity))             $config->cancellation_create_activity             = false;
    if (!isset($config->cancellation_create_activity_type_id))     $config->cancellation_create_activity_type_id     = 37;
    if (!isset($config->cancellation_create_activity_subject))     $config->cancellation_create_activity_subject     = E::ts("Follow-up SEPA Cancellation");
    if (!isset($config->cancellation_create_activity_assignee_id)) $config->cancellation_create_activity_assignee_id = 0; // will be replaced with current user
    if (!isset($config->cancellation_create_activity_text))        $config->cancellation_create_activity_text        = '';
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $data_parsed = $btx->getDataParsed();
    $probability = 1.0 - $this->getPenalty($btx);
    $cancellation_mode = ((bool) $config->cancellation_enabled) && ($btx->amount < 0);
    $this->contribution = NULL;

    // look for the 'sepa_mandate' key
    if (empty($data_parsed['sepa_mandate'])) return null;

    // now load the mandate
    $mandate_reference = $data_parsed['sepa_mandate'];
    $mandate = civicrm_api('SepaMandate', 'getsingle', array('version'=>3, 'reference'=>$mandate_reference));
    if (!empty($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(E::ts("Couldn't load SEPA mandate for reference %s"), $mandate_reference), E::ts('Error'), 'error');
      return null;
    }

    // find the contribution
    if ($mandate['type']=='OOFF' && $mandate['entity_table']=='civicrm_contribution') {
      $contribution_id = $mandate['entity_id'];


    } elseif ($mandate['entity_table']=='civicrm_contribution_recur') {
      $contribution_recur_id = $mandate['entity_id'];

      if (empty($data_parsed['sepa_batch'])) {
        // NO group information given -> try to find by date
        $value_date = strtotime($btx->value_date);
        if ($cancellation_mode) {
          $earliest_date = date('Ymdhis', strtotime($config->cancellation_date_minimum, $value_date));
          $latest_date = date('Ymdhis', strtotime($config->cancellation_date_maximum, $value_date));
        } else {
          $earliest_date = date('Ymdhis', strtotime($config->received_date_minimum, $value_date));
          $latest_date = date('Ymdhis', strtotime($config->received_date_maximum, $value_date));
        }

        $contribution_id = 0;
        $find_contribution_query = "
            SELECT  id
            FROM    civicrm_contribution
            WHERE   contribution_recur_id = {$contribution_recur_id}
            AND     receive_date <= DATE('$latest_date')
            AND     receive_date >= DATE('$earliest_date');";
        $found_contribution = CRM_Core_DAO::executeQuery($find_contribution_query);
        while ($found_contribution->fetch()) {
          if (!$contribution_id) {
            $contribution_id = $found_contribution->id;
          } else {
            // this is the second contribution found!
            CRM_Core_Session::setStatus(E::ts("There was more than one matching contribution found! Try to configure the plugin with a smaller search time span."), E::ts('Error'), 'error');
            return null;
          }
        }
        if (!$contribution_id) {
          // no contribution found
          CRM_Core_Session::setStatus(E::ts("There was no matching contribution! Try to configure the plugin with a larger search time span."), E::ts('Error'), 'error');
          return null;
        }

      } else {
        // we have the reference to the group -> that should make it unique
        $find_contribution_query = "
            SELECT  civicrm_contribution.id
            FROM    civicrm_contribution
            LEFT JOIN civicrm_sdd_contribution_txgroup ON civicrm_sdd_contribution_txgroup.contribution_id = civicrm_contribution.id
            LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
            WHERE   civicrm_contribution.contribution_recur_id = %1
            AND     civicrm_sdd_txgroup.reference = %2;";
        $contribution_id = CRM_Core_DAO::singleValueQuery($find_contribution_query, array(
            1 => array($contribution_recur_id, 'Integer'),
            2 => array($data_parsed['sepa_batch'], 'String')));

        if (!$contribution_id) {
          // no contribution found
          CRM_Core_Session::setStatus(E::ts("There is no contribution for mandate '{$data_parsed['sepa_mandate']}' in group '{$data_parsed['sepa_batch']}'"), E::ts('Error'), 'error');
          return null;
        }
      }


    } else {
      $this->logMessage("Bad mandate type.", 'warn');
      return null;
    }

    // now, let's have a look at this contribution and its contact...
    $contribution = civicrm_api('Contribution', 'getsingle', array('id'=>$contribution_id, 'version'=>3));
    if (!empty($contribution['is_error'])) {
        CRM_Core_Session::setStatus(E::ts("The contribution connected to this mandate could not be read."), E::ts('Error'), 'error');
        return null;
    }
    $contact = civicrm_api('Contact', 'getsingle', array('id'=>$contribution['contact_id'], 'version'=>3));
    if (!empty($contact['is_error'])) {
        CRM_Core_Session::setStatus(E::ts("The contact connected to this mandate could not be read."), E::ts('Error'), 'error');
        return null;
    }

    // now: create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setParameter('contribution_id', $contribution_id);
    $suggestion->setParameter('contact_id', $contribution['contact_id']);
    $suggestion->setParameter('mandate_id', $mandate['id']);
    $suggestion->setParameter('mandate_reference', $mandate_reference);

    if (!$cancellation_mode) {
      // STANDARD SUGGESTION:
      $suggestion->setTitle(E::ts("SEPA SDD Transaction"));

      // add penalties for deviations in amount,status,deleted contact
      if ($btx->amount != $contribution['total_amount']) {
        $suggestion->addEvidence($config->deviation_penalty, E::ts("The contribution does not feature the expected amount."));
        $probability -= $config->deviation_penalty;
      }
      $status_inprogress = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'In Progress');
      if ($contribution['contribution_status_id'] != $status_inprogress) {
        $suggestion->addEvidence($config->deviation_penalty, E::ts("The contribution does not have the expected status 'in Progress'."));
        $probability -= $config->deviation_penalty;
      }
      if (!empty($contact['contact_is_deleted'])) {
        $suggestion->addEvidence($config->deviation_penalty, E::ts("The contact this mandate belongs to has been deleted."));
        $probability -= $config->deviation_penalty;
      }

    } else {
      // CANCELLATION SUGGESTION:
      $suggestion->setTitle(E::ts("Cancel SEPA SDD Transaction"));
      $suggestion->setParameter('cancellation_mode', $cancellation_mode);
      $suggestion->setParameter('contribution_status_id', $contribution['contribution_status_id']);


      // check contribution status (see BANKING-135)
      $cancellation_status_penalty = NULL;
      $contribution_status_id      = $contribution['contribution_status_id'];
      if (is_array($config->cancellation_status_penalty)) {
        if (isset($config->cancellation_status_penalty[$contribution_status_id])) {
          $cancellation_status_penalty = (float) $config->cancellation_status_penalty[$contribution_status_id];
        }
      } else {
        if (isset($config->cancellation_status_penalty->$contribution_status_id)) {
          $cancellation_status_penalty = (float) $config->cancellation_status_penalty->$contribution_status_id;
        }
      }
      if ($cancellation_status_penalty === NULL) {
        // the status is not in the list => don't even create a suggestion
        $this->logMessage("Unmapped contributions status [{$contribution_status_id}] encountered. No suggestion generated!", 'warn');
        return NULL;
      }
      $probability -= $cancellation_status_penalty;


      // calculate penalties (based on CRM_Banking_PluginImpl_Matcher_ExistingContribution::rateContribution)
      $contribution_amount = $contribution['total_amount'];
      $target_amount = -$context->btx->amount;
      $amount_range_rel = $contribution_amount * ($config->cancellation_amount_relative_maximum - $config->cancellation_amount_relative_minimum);
      $amount_range_abs = $config->cancellation_amount_absolute_maximum - $config->cancellation_amount_absolute_minimum;
      $amount_range = max($amount_range_rel, $amount_range_abs);
      $amount_delta = $contribution_amount - $target_amount;

      // check for amount limits
      if ($amount_range) {
        $penalty = $config->cancellation_amount_penalty * (abs($amount_delta) / $amount_range);
        if ($penalty > $config->cancellation_penalty_threshold) {
          $suggestion->addEvidence($config->cancellation_amount_penalty, E::ts("The cancellation fee, i.e. the deviation from the original amount, is not in the specified range."));
          $probability -= $penalty;
        }
      }

      // add general cancellation penalty, if set
      $probability -= (float) $config->cancellation_general_penalty;

      // generate cancellation extra parameters
      if ($config->cancellation_cancel_reason) {
        // determine the cancel reason
        if (empty($data_parsed[$config->cancellation_cancel_reason_source])) {
          $suggestion->setParameter('cancel_reason', $config->cancellation_cancel_reason_default);
        } else {
          $suggestion->setParameter('cancel_reason', $data_parsed[$config->cancellation_cancel_reason_source]);
        }
      }
      if ($config->cancellation_cancel_fee) {
        // calculate / determine the cancellation fee
        try {
          $meval = new EvalMath();
          // first initialise variables 'difference' and 'source'
          $meval->evaluate("difference = -{$btx->amount} - {$contribution_amount}");
          if (empty($config->cancellation_cancel_fee_source) || empty($data_parsed[$config->cancellation_cancel_fee_source])) {
            $meval->evaluate("source = 0.0");
          } else {
            $meval->evaluate("source = {$data_parsed[$config->cancellation_cancel_fee_source]}");
          }
          $suggestion->setParameter('cancel_fee', number_format($meval->evaluate($config->cancellation_cancel_fee_default),2));
        } catch (Exception $e) {
          $this->logMessage("Couldn't calculate cancellation_fee. Error was: {$e->getMessage()}", 'error');
        }
      }
    }

    // store it
    $suggestion->setProbability($probability);
    $btx->addSuggestion($suggestion);

    return $this->_suggestions;
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
  public function execute($match, $btx) {
    $this->contribution = NULL;
    $cancellation_mode = $match->getParameter('cancellation_mode');
    if (!empty($cancellation_mode)) {
      // CANCELLATION is an entirely different process...
      return $this->executeCancellation($match, $btx);
    }

    $contribution_id = $match->getParameter('contribution_id');
    $status_pending = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Pending');
    $status_inprogress = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'In Progress');
    $status_completed = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');

    // Compatibility with CiviCRM < 4.7.0
    if (version_compare(CRM_Utils_System::version(), '4.7.0', '<')) {
      // Fix contribution, if it has no financial transactions. (happens due to a status-bug in civicrm)
      // in this case, set the status back to 'Pending', no 'is_pay_later'
      // This, however, would cause problems in later CiviCRM versions, see BANKING-243, hence the version check
      $fix_rotten_contribution_sql = "
      UPDATE
        civicrm_contribution
      SET
        contribution_status_id=$status_pending, is_pay_later=0
      WHERE
          id = $contribution_id
      AND NOT (   SELECT count(entity_id)
                  FROM civicrm_entity_financial_trxn
                  WHERE entity_table='civicrm_contribution'
                  AND   entity_id = $contribution_id
              );";
      CRM_Core_DAO::executeQuery($fix_rotten_contribution_sql);
    }

    // look up the txgroup
    $txgroup_query = civicrm_api('SepaContributionGroup', 'getsingle', array('contribution_id'=>$contribution_id, 'version'=>3));
    if (!empty($txgroup_query['is_error'])) {
      CRM_Core_Session::setStatus(E::ts("Contribution is NOT member in exactly one SEPA transaction group!"), E::ts('Error'), 'error');
      return;
    }
    $txgroup_id = $txgroup_query['txgroup_id'];

    // now we can set the status to 'Completed'
    $query = array('version' => 3, 'id' => $contribution_id);
    $query['contribution_status_id'] = $status_completed;
    $query['receive_date'] = date('Ymdhis', strtotime($btx->value_date));
    $query = array_merge($query, $this->getPropagationSet($btx, $match, 'contribution'));   // add propagated values
    CRM_Banking_Helpers_IssueMitigation::mitigate358($query);
    $result = civicrm_api('Contribution', 'create', $query);

    if (isset($result['is_error']) && $result['is_error']) {
      $this->logMessage("Couldn't modify contribution, error was: ".$result['error_message'], 'error');
      CRM_Core_Session::setStatus(E::ts("Couldn't modify contribution."), E::ts('Error'), 'error');

    } else {
      // everything seems fine, save the account
      if (!empty($result['values'][$contribution_id]['contact_id'])) {
        $this->storeAccountWithContact($btx, $result['values'][$contribution_id]['contact_id']);
      } elseif (!empty($result['values'][0]['contact_id'])) {
        $this->storeAccountWithContact($btx, $result['values'][0]['contact_id']);
      }

      // link the contribution
      CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution_id);

      // close transaction group if this was the last transaction
      $open_contributions_in_group_sql = "
      SELECT
        count(c2group.id) AS open_count
      FROM
        civicrm_sdd_contribution_txgroup c2group
      LEFT JOIN
        civicrm_contribution contribution ON c2group.contribution_id=contribution.id
      WHERE
          c2group.txgroup_id = $txgroup_id
      AND contribution.contribution_status_id IN ($status_pending,$status_inprogress);";
      $result = CRM_Core_DAO::executeQuery($open_contributions_in_group_sql);
      if ($result->fetch() && $result->open_count==0) {
        // set this group's status to 'received'
        $group_status_id_received = banking_helper_optionvalue_by_groupname_and_name('batch_status', 'Received');
        if ($group_status_id_received) {
          $txgroup_query = array('id'=>$txgroup_id, 'status_id'=>$group_status_id_received, 'version'=>3);
          $close_result = civicrm_api('SepaTransactionGroup', 'create', $txgroup_query);
          if (!empty($close_result['is_error'])) {
            CRM_Core_Session::setStatus(sprintf("Cannot mark transaction group [%s] received. Error: %s", $txgroup_id, $close_result['error_message']), E::ts('Error'), 'error');
            return;
          }
          $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', $txgroup_query);
          if (!empty($txgroup['is_error'])) {
            CRM_Core_Session::setStatus(sprintf("Cannot mark transaction group [%s] received. Error: %s", $txgroup_id, $txgroup['error_message']), E::ts('Error'), 'error');
            return;
          }
          CRM_Core_Session::setStatus(sprintf(E::ts("SEPA transaction group '%s' was marked as received."), $txgroup['reference']), E::ts('Success'), 'info');
        }
      }
    }

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($match, $btx);
    return true;
  }

  /**
   * Execute the previously generated cancellation suggestion,
   *   and close the transaction
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   *   the suggestion to be executed
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   */
  public function executeCancellation($match, $btx) {
    $config = $this->_plugin_config;
    $contribution_id = $match->getParameter('contribution_id');
    $contribution_status_id = $match->getParameter('contribution_status_id');
    $mandate_id = $match->getParameter('mandate_id');

    // load contribution to double-check status (see BANKING-135)
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    if ($contribution_status_id) {
      if ($contribution['contribution_status_id'] != $contribution_status_id) {
        CRM_Core_Session::setStatus(E::ts("Contribution status has been modified."), E::ts('Error'), 'error');
        return FALSE;
      }
    }
    // store contribution for reference (e.g. in propagation)
    $this->contribution = $contribution;

    // set the status to 'Cancelled'
    if (!empty($config->cancelled_contribution_status_id)) {
      $status_cancelled = $config->cancelled_contribution_status_id;
    } else {
      $status_cancelled = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Cancelled');
    }

    $this->logger->setTimer('sepa_mandate_cancel_contribution');
    $query = array('version' => 3, 'id' => $contribution_id);
    $query['contribution_status_id'] = $status_cancelled;
    $query['cancel_date'] = date('Ymdhis', strtotime($btx->value_date));
    $query['currency'] = $contribution['currency'];
    $query = array_merge($query, $this->getPropagationSet($btx, $match, 'contribution', $config->cancellation_value_propagation));   // add propagated values
    if (empty($query['cancel_reason'])) // add default values
      $query['cancel_reason'] = $config->cancellation_default_reason;
    if ($config->cancellation_cancel_reason) {
      $query['cancel_reason'] = $match->getParameter('cancel_reason');
    }
    $this->logMessage("SepaMandate matcher calling Contribution.create: " . json_encode($query), 'debug');
    CRM_Banking_Helpers_IssueMitigation::mitigate358($query);
    $result = civicrm_api('Contribution', 'create', $query);
    $this->logTime('Cancel Contribution', 'sepa_mandate_cancel_contribution');

    if (isset($result['is_error']) && $result['is_error']) {
      $this->logMessage("Couldn't modify contribution, error was: ".$result['error_message'], 'error');
      CRM_Core_Session::setStatus(E::ts("Couldn't modify contribution."), E::ts('Error'), 'error');
      return FALSE;

    } else {
      // now for the mandate...
      $contribution = civicrm_api('Contribution', 'getsingle', array('version'=>3, 'id' => $contribution_id));
      if (!empty($contribution['is_error'])) {
        $this->logMessage("Couldn't load contribution, error was: ".$result['error_message'], 'error');
        CRM_Core_Session::setStatus(E::ts("Couldn't modify contribution."), E::ts('Error'), 'error');

      } else {
          // compatibility: contribution_payment_instrument isn't set any more...
        if (!empty($contribution['payment_instrument'])) {
          $contribution['contribution_payment_instrument'] = $contribution['payment_instrument'];
        }

        if (   'OOFF' == $contribution['contribution_payment_instrument']
            && !empty($config->cancellation_update_mandate_status_OOFF)) {
          // everything seems fine, adjust the mandate's status
          $query = array('version' => 3, 'id' => $mandate_id);
          $query['status'] = $config->cancellation_update_mandate_status_OOFF;
          $query = array_merge($query, $this->getPropagationSet($btx, $match, 'mandate'));   // add propagated values
          $result = civicrm_api('SepaMandate', 'create', $query);
          if (!empty($result['is_error'])) {
            $this->logMessage("Couldn't modify mandate, error was: ".$result['error_message'], 'error');
            CRM_Core_Session::setStatus(E::ts("Couldn't modify mandate."), E::ts('Error'), 'error');
            return FALSE;
          }
        } elseif (   'RCUR' == $contribution['contribution_payment_instrument']
                  && !empty($config->cancellation_update_mandate_status_RCUR)) {
          // everything seems fine, adjust the mandate's status
          $query = array('version' => 3, 'id' => $mandate_id);
          $query['status'] = $config->cancellation_update_mandate_status_RCUR;
          $query = array_merge($query, $this->getPropagationSet($btx, $match, 'mandate'));   // add propagated values
          $result = civicrm_api('SepaMandate', 'create', $query);
          if (!empty($result['is_error'])) {
            $this->logMessage("Couldn't modify mandate, error was: ".$result['error_message'], 'error');
            CRM_Core_Session::setStatus(E::ts("Couldn't modify mandate."), E::ts('Error'), 'error');
            return FALSE;
          }
        }
      }
    }

    // create activity if wanted
    if ($config->cancellation_create_activity) {
      // gather some information to put in the text
      $smarty_vars = array();
      $smarty_vars['contribution']  = $contribution;
      $smarty_vars['cancel_fee']    = $match->getParameter('cancel_fee');
      $smarty_vars['cancel_reason'] = $match->getParameter('cancel_reason');

      // load the mandate
      $mandate = civicrm_api('SepaMandate', 'getsingle', array('id' => $mandate_id, 'version' => 3));
      if ($mandate['type']=='RCUR' && $mandate['entity_table'] == 'civicrm_contribution_recur') {
        // add some additional parameters for RCUR (see #256)
        $rcur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $mandate['entity_id']));
        foreach ($rcur as $key => $value) {
          $mandate["rcur_{$key}"] = $value;
        }

        // if CiviSEPA is present, add a nicer
        if (method_exists('CRM_Utils_SepaOptionGroupTools', 'getFrequencyText')) {
          $mandate["rcur_frequency"] = CRM_Utils_SepaOptionGroupTools::getFrequencyText($rcur['frequency_interval'], $rcur['frequency_unit'], TRUE);
        } else {
          if ($rcur['frequency_interval'] == 1) {
            if ($rcur['frequency_unit'] == 'month') {
              $mandate["rcur_frequency"] = E::ts("monthly");
            } elseif ($rcur['frequency_unit'] == 'year') {
              $mandate["rcur_frequency"] = E::ts("annually");
            } else {
              $mandate["rcur_frequency"] = $rcur['frequency_unit'].'ly';
            }
          } else {
            if ($rcur['frequency_unit'] == 'month') {
              $mandate["rcur_frequency"] = E::ts("every %1 months", [1 => $rcur['frequency_interval']]);
            } elseif ($rcur['frequency_unit'] == 'year') {
              $mandate["rcur_frequency"] = E::ts("every %1 years", [1 => $rcur['frequency_interval']]);
            } elseif ($rcur['frequency_unit'] == 'week') {
              $mandate["rcur_frequency"] = E::ts("every %1 weeks", [1 => $rcur['frequency_interval']]);
            } else {
              $mandate["rcur_frequency"] = E::ts("every %1 %2", [1 => $rcur['frequency_interval'], 2 => $rcur['frequency_unit']]);
            }
          }
        }
      }
      $smarty_vars['mandate'] = $mandate;

      // load the contact
      $contact = civicrm_api('Contact', 'getsingle', array('id' => $contribution['contact_id'], 'version' => 3));
      $smarty_vars['contact'] = $contact;

      // count the cancelled contributions connected to this mandate
      $cancelled_contribution_count = 0;
      $current_contribution_date = date('Ymdhis', strtotime($contribution['receive_date']));
      if ($mandate['type']=='RCUR') {
        $query = "SELECT contribution_status_id
                  FROM civicrm_contribution
                  WHERE contribution_recur_id = {$mandate['entity_id']}
                    AND receive_date <= '$current_contribution_date'
                  ORDER BY receive_date DESC;";
        $status_list = CRM_Core_DAO::executeQuery($query);
        while ($status_list->fetch()) {
          if ($status_list->contribution_status_id == $status_cancelled) {
            $cancelled_contribution_count += 1;
          } else {
            break;
          }
        }
      }
      $smarty_vars['cancelled_contribution_count'] = $cancelled_contribution_count;

      // look up contact if not set
      $user_id = CRM_Core_Session::singleton()->get('userID');
      if (empty($config->cancellation_create_activity_assignee_id)) {
        $assignedTo = $user_id;
      } else {
        $assignedTo = (int) $config->cancellation_create_activity_assignee_id;
      }

      // compile the text
      $smarty = CRM_Banking_Helpers_Smarty::singleton();
      $smarty->pushScope($smarty_vars);
      if (empty($config->cancellation_create_activity_text)) {
        $details = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/SepaMandate.activity.tpl');
      } else {
        if (substr($config->cancellation_create_activity_text, 0, 5) == 'file:') {
          // this is a template file path
          $details = $smarty->fetch(substr($config->cancellation_create_activity_text, 5));
        } else {
          // this contains the template date itself:
          $details = $smarty->fetch("string:" . $config->cancellation_create_activity_text);
        }
      }
      $smarty->popScope();

      // at some point, all newlines got replaced with <br/> - we don't want that:
      $details = str_replace("\n", '', $details);

      $activity_parameters = array(
        'version'            => 3,
        'activity_type_id'   => $config->cancellation_create_activity_type_id,
        'subject'            => $config->cancellation_create_activity_subject,
        'status_id'          => 1, // planned
        'activity_date_time' => date('YmdHis'),
        'source_contact_id'  => $user_id,
        'target_contact_id'  => $contact['id'],
        'details'            => $details
      );
      $activity = CRM_Activity_BAO_Activity::create($activity_parameters);

      $assignment_parameters = array(
        'activity_id'    => $activity->id,
        'contact_id'     => $assignedTo,
        'record_type_id' => 1  // ASSIGNEE
      );
      $assignment = CRM_Activity_BAO_ActivityContact::create($assignment_parameters);
    }

    // link contribution
    CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution_id);

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($match, $btx);
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
    $config = $this->_plugin_config;
    if ($match->getParameter('cancellation_mode')) {
      // store potentially modified extended cancellation values
      if ($config->cancellation_cancel_reason) {
        $match->setParameter('cancel_reason', $parameters['cancel_reason']);
      }
      if ($config->cancellation_cancel_fee) {
        $match->setParameter('cancel_fee', number_format((float) $parameters['cancel_fee'], 2));
      }
    }
  }


  /**
   * Fetch a named propagation object.
   * @see CRM_Banking_PluginModel_BtxBase::getPropagationValue
   */
  public function getPropagationObject($name, $btx) {
    // in this default implementation, no extra objects are provided
    // please overwrite in the plugin implementation
    switch ($name) {
      case 'contribution':
        // currently only works for cancellation
        return $this->contribution;

      default:
        // nothing to do here
        break;
    }
    return parent::getPropagationObject($name, $btx);
  }

    /**
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    $config = $this->_plugin_config;
    $smarty_vars = array();

    // load the contribution
    $contribution_id   = $match->getParameter('contribution_id');
    $mandate_id        = $match->getParameter('mandate_id');
    $mandate_reference = $match->getParameter('mandate_reference');
    $cancellation_mode = $match->getParameter('cancellation_mode');
    $cancellation_mode = !(empty($cancellation_mode));

    $smarty_vars['contribution_id']   = $contribution_id;
    $smarty_vars['mandate_id']        = $mandate_id;
    $smarty_vars['mandate_reference'] = $mandate_reference;
    $smarty_vars['cancellation_mode'] = $cancellation_mode;

    $result = civicrm_api('Contribution', 'get', array('version' => 3, 'id' => $contribution_id));
    if (isset($result['id'])) {
      // gather information
      $contribution = $result['values'][$result['id']];
      $contact_id   = $contribution['contact_id'];
      $contact_link = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid=$contact_id");
      $smarty_vars['contribution']      = $contribution;
      $smarty_vars['contact_id']        = $contact_id;
      $smarty_vars['contact_html']      = "<a href=\"$contact_link\" target=\"_blank\">{$contribution['display_name']} [$contact_id]</a>";
      $smarty_vars['mandate_link']      = CRM_Utils_System::url("civicrm/sepa/xmandate", "mid=$mandate_id");
      $smarty_vars['contribution_link'] = CRM_Utils_System::url("civicrm/contact/view/contribution", "reset=1&id=$contribution_id&cid=$contact_id&action=view");
      $smarty_vars['create_activity']   = $config->cancellation_create_activity;

      // add warnings, if any
      $smarty_vars['warnings'] = $match->getEvidence();

      // add cancellation extra parameters
      if ($cancellation_mode) {
        $smarty_vars['cancellation_cancel_reason'] = $config->cancellation_cancel_reason;
        if ($config->cancellation_cancel_reason) {
          $smarty_vars['cancel_reason']      = $match->getParameter('cancel_reason');
          $smarty_vars['cancel_reason_edit'] = $config->cancellation_cancel_reason_edit;
        }
        $smarty_vars['cancellation_cancel_fee']    = $config->cancellation_cancel_fee;
        if ($config->cancellation_cancel_fee) {
          $smarty_vars['cancel_fee']         = $match->getParameter('cancel_fee');
          $smarty_vars['cancel_fee_edit']    = $config->cancellation_cancel_fee_edit;
        }
      }

    } else {
      // CONTRIBUTION NOT FOUND!
      $smarty_vars['error'] = E::ts("Internal error! Cannot find contribution #").$match->getParameter('contribution_id');
    }

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/SepaMandate.suggestion.tpl');
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
    $contact_id = $match->getParameter('contact_id');
    if (empty($contact_id)) {
      // this information has not been stored (old matcher version)
      $result = civicrm_api('Contribution', 'get', array('version' => 3, 'id' => $match->getParameter('contribution_id')));
      if (isset($result['id'])) {
        $contribution = $result['values'][$result['id']];
        $contact_id   = $contribution['contact_id'];
        $smarty_vars['contact_id'] = $contact_id;
      } else {
        // TODO: error handling?
        $smarty_vars['contact_id'] = 0;
      }
    } else {
      $smarty_vars['contact_id'] = $contact_id;
    }
    $smarty_vars['cancellation_mode'] = $match->getParameter('cancellation_mode');
    $smarty_vars['cancel_fee']        = $match->getParameter('cancel_fee');
    $smarty_vars['cancel_reason']     = $match->getParameter('cancel_reason');

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/SepaMandate.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }
}

