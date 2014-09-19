<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 P. Delbar                      |
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
 * File for the CiviCRM APIv3 banking account manipulation
 *
 * @package CiviBanking Extension
 * @subpackage API_Accounts
 */

/**
 * Include utility functions
 */
//TODO: include DAOs, e.g.: require_once 'CRM/Banking/DAO/BankingAccount.php';

/**
 * Add or update a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_create($params) {
  return _civicrm_api3_basic_create("CRM_Banking_BAO_BankAccount", $params);
}
  
/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_banking_account_create_spec(&$params) {
    $params['created_date']['api.required'] = 0;
    $params['modified_date']['api.required'] = 0;
    $params['description']['api.default'] = "";
    $params['data_raw']['api.default'] = "{}";
    $params['data_parsed']['api.default'] = "{}";
}

/**
 * Get a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_get($params) {
  return _civicrm_api3_basic_get("CRM_Banking_BAO_BankAccount", $params);
}

/**
 * Delete a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_delete($params) {
  return _civicrm_api3_basic_delete("CRM_Banking_BAO_BankAccount", $params);
}

