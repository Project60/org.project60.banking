<?php
// $Id$
/*
 +--------------------------------------------------------------------+
 | Project60 version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright ???????? (c) 2004-2013                                   |
 +--------------------------------------------------------------------+
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * File for the CiviCRM APIv3 banking_payment functions
 *
 * @package CiviBanking
 *
 */


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
    if (empty($from) || $empty($to)) {
      return civicrm_api3_create_error("Something's wrong with your time parameter. Expected format is 'hh:mm-hh:mm'.");
    }

    // now check the time
    if ($from > $now) {
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