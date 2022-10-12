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
 * File for the CiviCRM APIv3 banking_payment functions
 *
 * @package CiviBanking
 *
 */


/**
 * Add an BankingPluginInstance
 *
 * Allowed @params array keys are:
 *
 *
 * @return array API result array
 * {@getfields plugin_type_id}
 * {@getfields plugin_class_id}
 * @access public
 */
function civicrm_api3_banking_plugin_instance_create($params) {
  return _civicrm_api3_basic_create("CRM_Banking_BAO_PluginInstance", $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_banking_plugin_instance_create_spec(&$params) {
    $params['plugin_type_id']['api.required'] = 1;
    $params['plugin_class_id']['api.required'] = 1;
    $params['name']['api.default'] = "Unknown";
    $params['description']['api.default'] = "";
    $params['enabled']['api.default'] = 1;
    $params['weight']['api.default'] = 100;
    $params['config']['api.default'] = "{}";
    $params['state']['api.default'] = 0;
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
function civicrm_api3_banking_plugin_instance_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO("civicrm_api3_plugin_instance_delete"), $params);
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
function civicrm_api3_banking_plugin_instance_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO("civicrm_api3_plugin_instance_get"), $params);
}


