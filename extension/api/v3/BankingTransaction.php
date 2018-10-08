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

/**
 * File for the CiviCRM APIv3 banking_payment functions
 *
 * @package CiviBanking
 *
 */
require_once 'CRM/Banking/Helpers/OptionValue.php';


/**
 * Add an BankingTransaction for a contact
 *
 * Allowed @params array keys are:
 *
 * @example BankingTransaction.php Standard Create Example
 *
 * @return array API result array
 * {@getfields banking_transaction_create}
 * @access public
 */
function civicrm_api3_banking_transaction_create($params) {
  return _civicrm_api3_basic_create("CRM_Banking_BAO_BankTransaction", $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_banking_transaction_create_spec(&$params) {
    $params['bank_reference']['api.required'] = 1;
    $params['amount']['api.default'] = "666";
    $params['type_id']['api.default'] = "0";
    $params['status_id']['api.default'] = "0";
    $params['data_raw']['api.default'] = "{}";
    $params['data_parsed']['api.default'] = "{}";
}

/**
 * Deletes an existing BankingTransaction
 *
 * @param  array  $params
 *
 * @example BankingTransaction.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields banking_transaction_delete}
 * @access public
 */
function civicrm_api3_banking_transaction_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Banking_BAO_BankTransaction', $params);
}

/**
 * Retrieve one or more BankingTransactions
 *
 * @param  array input parameters
 *
 *
 * @example BankingTransaction.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields banking_transaction_get}
 * @access public
 */
function civicrm_api3_banking_transaction_get($params) {
  return _civicrm_api3_basic_get('CRM_Banking_BAO_BankTransaction', $params);
}


/**
 * Deletes a given list of bank statments and transactions
 *  used as AJAX call
 *
 * @param  list    list of bank_tx ids to process
 * @param  s_list  list of bank_tx_batch ids to process
 *
 * @return  array api result array
 * @access public
 */
function civicrm_api3_banking_transaction_deletelist($params) {
  $result = array('tx_count' => 0, 'tx_batch_count' => 0);

  // first, delete the indivdual transactions
  $tx_ids = _civicrm_api3_banking_transaction_getTxIDs($params);
  foreach ($tx_ids as $tx_id) {
    civicrm_api3('BankingTransaction', 'delete', array('id' => $tx_id));
    $result['tx_count'] += 1;
  }

  // then, delete the (now empty) statmets (tx_batches)
  if (!empty($params['s_list'])) {
    $tx_batch_ids = explode(',', $params['s_list']);
    foreach ($tx_batch_ids as $tx_batch_id) {
      $tx_batch_id = (int) $tx_batch_id;
      if (!empty($tx_batch_id)) {
        civicrm_api3('BankingTransactionBatch', 'delete', array('id' => $tx_batch_id));
        $result['tx_batch_count'] += 1;
      }
    }
  }

  return civicrm_api3_create_success($result);
}

/**
 * Analyses the given bank transactions
 *  used as AJAX call
 *
 * @param  list    list of bank_tx ids to process
 * @param  s_list  list of bank_tx_batch ids to process
 *
 * @return  array api result array
 * @access public
 */
function civicrm_api3_banking_transaction_analyselist($params) {
  // extract payment IDs from parameters
  $tx_ids = _civicrm_api3_banking_transaction_getTxIDs($params);
  if (empty($tx_ids)) {
    return civicrm_api3_create_error("Something's wrong with your parameters. No payments found.");
  }

  // filter for non-closed statements
  $payment_states  = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
  $state_ignored   = (int) $payment_states['ignored']['id'];
  $state_processed = (int) $payment_states['processed']['id'];
  $list_string = implode(',', $tx_ids);
  $filter_query = "SELECT `id`
                   FROM `civicrm_bank_tx`
                   WHERE `status_id` NOT IN ({$state_ignored},{$state_processed})
                     AND `id` IN ($list_string);";
  $filter_result = CRM_Core_DAO::executeQuery($filter_query);
  $filtered_list = array();
  while ($filter_result->fetch()) {
    $filtered_list[] = $filter_result->id;
  }

  // check if we should use a runner
  if (!empty($params['use_runner']) && count($filtered_list) >= $params['use_runner']) {
    // use the runner instead of the immediate execution
    $runner_url = CRM_Banking_Helpers_AnalysisRunner::createRunner($filtered_list, $params['back_url']);
    $result = array(
      'payment_count'   =>  count($filtered_list),
      'processed_count' =>  0,
      'skipped_count'   =>  0,
      'time'            =>  0,
      'timed_out'       =>  0,
      'runner_url'      => $runner_url);
    return civicrm_api3_create_success($result);
  }

  // calculate a timeout, so we wouldn't get killed off
  $now = strtotime('now');
  $max_execution_time = ini_get('max_execution_time');
  if (empty($max_execution_time)) {
    $max_execution_time = 10 * 60; // 10 minutes
  } else {
    $max_execution_time = min(10*60, (int) $max_execution_time*0.9);
  }
  $timeout = strtotime("+$max_execution_time seconds");

  // now run the matchers
  $engine = CRM_Banking_Matcher_Engine::getInstance();
  $timed_out = 0;
  $processed_count = 0;
  foreach ($filtered_list as $pid) {
    $engine->match($pid);
    $processed_count += 1;
    if (strtotime("now") > $timeout) {
      $timed_out = 1;
      break;
    }
  }

  // done. create a result.
  $after_exec = strtotime('now');
  $payment_count   = count(explode(',', $list_string));
  $result = array(
    'payment_count'   =>  $payment_count,
    'processed_count' =>  $processed_count,
    'skipped_count'   =>  $payment_count - count($filtered_list),
    'time'            =>  ($after_exec - $now),
    'timed_out'       =>  $timed_out,
  );

  return civicrm_api3_create_success($result);
}

/**
 * Metadata for BankingTransaction.analyselist
 */
function _civicrm_api3_banking_transaction_analyselist_spec(&$spec) {
  $spec['list'] = array(
    'title'       => 'BTX ID List',
    'description' => 'List of bank transaction IDs, comma-separated',
    'required'    => FALSE,
    'type'        => CRM_Utils_Type::T_STRING,
  );
  $spec['s_list'] = array(
    'title'       => 'Statement ID List',
    'description' => 'List of bank statement IDs, comma-separated',
    'required'    => FALSE,
    'type'        => CRM_Utils_Type::T_STRING,
  );
  $spec['use_runner'] = array(
    'title'       => 'Runner Threshold (count)',
    'description' => 'Of the number of transactions to be anaylysed exceeds this number, the runner is used.',
    'required'    => FALSE,
    'type'        => CRM_Utils_Type::T_INT,
  );
  $spec['back_url'] = array(
    'title'       => 'Return URL',
    'description' => 'If the runner is used, this URL is set as return path',
    'required'    => FALSE,
    'type'        => CRM_Utils_Type::T_STRING,
  );
}


/**
 * Analyses the oldest (by value_date) <n> unprocessed bank transactions
 *
 * @param  array input parameters:
 *           time: a range <from_time>-<to_time> (24h) where this should apply (for scheduled job execution)
 *                    example: 23:00-04:00
 *           count: a limit for the amount of bank transactions to be processed - to avoid timeouts
 *
 * @return  array api result array
 * @access public
 */
function civicrm_api3_banking_transaction_analyseoldest($params) {
  // first: check time restrictions
  $now = strtotime('now');
  if (!empty($params['time'])) {
    // try to parse time
    $times = explode('-', $params['time']);
    $from = strtotime($times[0]);
    $to = strtotime($times[1]);
    if (empty($from) || empty($to)) {
      return civicrm_api3_create_error("Something's wrong with your time parameter. Expected format is 'hh:mm-hh:mm'.");
    }

    // now check the time
    if ($from < $to) {
      // this is a 'nomal' timing, e.g. 19:00-21:00
      if ($now < $from || $now > $to) {
        return civicrm_api3_create_success('Not active.');
      }
    } else {
      // this is an 'overnight' timing, e.g. 23:00-03:00
      if ($now < $from && $now > $to) {
        return civicrm_api3_create_success('Not active.');
      }
    }
  }

  // extract max_count parameter
  $max_count = 1000;
  if (!empty($params['count']) && ((int) $params['count']) > 0) {
    $max_count = (int) $params['count'];
  }

  // then execute
  $engine = CRM_Banking_Matcher_Engine::getInstance();
  $processed_count = $engine->bulkRun($max_count);

  // finally, compile the result
  $after_exec = strtotime('now');
  $result = array(
    'max_count' =>        $max_count,
    'processed_count' =>  $processed_count,
    'time'            =>  ($after_exec - $now),
  );
  if ($processed_count > 0) {
    $result['time_per_tx'] = ($after_exec - $now) / $processed_count;
  }

  return civicrm_api3_create_success($result);
}


/**
 * extracts the individual transaction IDs from the parameter set
 * @param  list    comma separated list of bank_tx ids to process
 * @param  s_list  comma separated list of bank_tx_batch ids to process
 *
 * @return array of IDs
 */
function _civicrm_api3_banking_transaction_getTxIDs($params) {
  // extract payment IDs from parameters
  $list_string = "";
  if (!empty($params['list'])) {
    $list_string .= $params['list'];
  }
  if (!empty($params['s_list'])) {
    if (!empty($list_string)) $list_string .= ',';
    $list_string .= CRM_Banking_Page_Payments::getPaymentsForStatements($params['s_list']);
  }

  // clean up the list
  $list = explode(',', $list_string);
  $tx_ids = array();
  foreach ($list as $tx_id) {
    $tx_id = (int) $tx_id;
    if (!empty($tx_id)) {
      $tx_ids[] = $tx_id;
    }
  }
  return $tx_ids;
}