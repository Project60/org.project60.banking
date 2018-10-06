<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 P. Delbar                      |
| Author: P. Delbar                                      |
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
 * File for the CiviCRM APIv3 banking_payment_batch functions
 *
 * @package CiviBanking
 *
 */


/**
 * Add an BankingTransactionBatch
 *
 * Allowed @params array keys are:
 *
 * @example BankingTransactionBatch.php Standard Create Example
 *
 * @return array API result array
 * {@getfields banking_transaction_create}
 * @access public
 */
function civicrm_api3_banking_transaction_batch_create($params) {
  return _civicrm_api3_basic_create('CRM_Banking_BAO_BankTransactionBatch', $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_banking_transaction_batch_create_spec(&$params) {
    // TODO: adjust
    $params['issue_date']['api.required'] = 1;
    $params['reference']['api.required'] = 1;
    $params['sequence']['api.default'] = 0;
    $params['tx_count']['api.default'] = 0;
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
function civicrm_api3_banking_transaction_batch_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Banking_BAO_BankTransactionBatch', $params);
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
function civicrm_api3_banking_transaction_batch_get($params) {
  return _civicrm_api3_basic_get('CRM_Banking_BAO_BankTransactionBatch', $params);
}



