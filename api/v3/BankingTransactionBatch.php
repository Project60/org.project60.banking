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

use Civi\Api4\BankTransactionBatch;
use Civi\Banking\Api4\Api3To4Util;

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
  $values = Api3To4Util::createValues(BankTransactionBatch::getEntityName(), $params);
  $resultValues = BankTransactionBatch::create($params['check_permissions'] ?? FALSE)
    ->setValues($values)
    ->execute()
    ->single();

  return civicrm_api3_create_success($resultValues, $params, 'BankTransactionBatch', 'create');
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
  $options = _civicrm_api3_get_options_from_params($params);

  $where = Api3To4Util::createWhere(BankTransactionBatch::getEntityName(), $params);
  $action = BankTransactionBatch::get($params['check_permissions'] ?? FALSE)
    ->setWhere($where)
    ->setLimit($options['limit'])
    ->setOffset($options['offset']);

  if ($options['is_count']) {
    $action->selectRowCount();
  }
  else {
    $action->setSelect(array_keys(array_filter($options['return'])));
    if (isset($options['sort'])) {
      [$sortFieldName, $sortDirection] = explode(' ', $options['sort']);
      $action->addOrderBy($sortFieldName, $sortDirection);
    }
  }

  $result = $action->execute();

  if ($options['is_count']) {
    return civicrm_api3_create_success(
      $result->countMatched(),
      $params,
      'BankTransactionBatch',
      'get'
    );
  }

  return civicrm_api3_create_success(
    $result->indexBy('id')->getArrayCopy(),
    $params,
    'BankTransactionBatch',
    'get'
  );
}
