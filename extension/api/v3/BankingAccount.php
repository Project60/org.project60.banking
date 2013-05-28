<?php
// $Id$

/*
  org.project60.banking extension for CiviCRM

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


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
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO( 'civicrm_api3_bank_account_get' ), $params);
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
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

