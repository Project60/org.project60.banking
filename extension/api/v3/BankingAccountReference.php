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
 * File for the CiviCRM APIv3 banking account manipulation
 *
 * @package CiviBanking Extension
 * @subpackage API_Accounts
 */

/**
 * BankingAccountReference.check has two functions:
 *  - check if the reference is valid
 *  - normalize the reference
 *
 * @param $reference            reference to be checked
 * @param $reference_type       id (not value) of the reference type OptionValue
 * @param $reference_type_name  name of reference type, e.g. NBAN_DE
 * 
 * @return 'is_valid'   0/1: is the given reference valid? 0 could also mean "not checked", see below
 *         'checked'    0/1: could the value be checked? If 0, there is no implementation for this type
 *         'normalised' 0/1: was the reference normalised?
 *         'reference'  the normalised reference
 *         'original'   the reference as queried
 */
function civicrm_api3_banking_account_reference_check($params) {
  if (empty($params['reference'])) {
    return civicrm_api3_create_error("No 'reference' parameter given.");
  }

  if (!empty($params['reference_type'])) {
    // look up reference_type_name
    $params['reference_type_name'] = civicrm_api3('OptionValue', 'getvalue', array('id' => ((int) $params['reference_type']), 'return' => 'name'));
  }

  if (empty($params['reference_type_name'])) {
    return civicrm_api3_create_error("No 'reference_type_name' parameter given.");
  }
  
  $reference = $params['reference'];
  $reply = array('original' => $reference);
  $result = CRM_Banking_BAO_BankAccountReference::normalise($params['reference_type_name'], $reference);
  if ($result===FALSE) {
    $reply['is_valid']   = 0;
    $reply['checked']    = 0;
    $reply['normalised'] = 0;
  } elseif ($result===0) {
    $reply['is_valid']   = 0;
    $reply['checked']    = 1;
    $reply['normalised'] = 0;
  } elseif ($result===1) {
    $reply['is_valid']   = 1;
    $reply['checked']    = 1;
    $reply['normalised'] = 0;
  } elseif ($result===2) {
    $reply['is_valid']   = 1;
    $reply['checked']    = 1;
    $reply['normalised'] = 1;    
  } else {
    return civicrm_api3_create_error("Internal error");
  }
  $reply['reference'] = $reference;
  return civicrm_api3_create_success($reply); 
}

/**
 * Add or update a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_reference_create($params) {
  return _civicrm_api3_basic_create("CRM_Banking_BAO_BankAccountReference", $params);
}
  
/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_banking_account_reference_create_spec(&$params) {
    $params['reference_type_id']['api.required'] = 1;
    $params['reference']['api.required'] = 1;
}

/**
 * Get a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_reference_get($params) {
  return _civicrm_api3_basic_get("CRM_Banking_BAO_BankAccountReference", $params);
}

/**
 * Delete a banking account entry
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        
 * @access public
 */
function civicrm_api3_banking_account_reference_delete($params) {
  return _civicrm_api3_basic_delete("CRM_Banking_BAO_BankAccountReference", $params);
}


