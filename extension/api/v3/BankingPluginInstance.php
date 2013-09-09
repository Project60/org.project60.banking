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


