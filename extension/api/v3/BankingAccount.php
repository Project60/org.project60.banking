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



/**
 * Get or create a bank account for a given contact
 * @return array (reference )
 * @access public
 */
function civicrm_api3_banking_account_getorcreate($params) {
  // sort out the reference type
  if (is_numeric($params['reference_type'])) {
    $reference_type_id = (int) $params['reference_type'];
  } else {
    $reference_type_id = civicrm_api3('OptionValue', 'getvalue', array(
      'option_group_id' => 'civicrm_banking.reference_types',
      'name'            => $params['reference_type'],
      'return'          => 'id'));
  }

  if (empty($reference_type_id)) {
    throw new Exception("Cannot process reference type '{$params['reference_type']}'");
  }

  // first: find any existing accounts
  $existing_ba_id = CRM_Core_DAO::singleValueQuery("
    SELECT ba.id AS ba_id
    FROM civicrm_bank_account ba
    LEFT JOIN civicrm_bank_account_reference ref ON ba.id = ref.ba_id
    WHERE ba.contact_id = %1
      AND ref.reference = %2
      AND ref.reference_type_id = %3
      LIMIT 1", array(
        1 => array($params['contact_id'], 'Integer'),
        2 => array($params['reference'], 'String'),
        3 => array($reference_type_id, 'Integer')));
  if ($existing_ba_id) {
    // this account exists!
    return civicrm_api3('BankingAccount', 'getsingle', array('id' => $existing_ba_id));

  } else {
    // create a new account

    // first: gather some data
    $bank_account_data = array();
    if (!empty($params['data_parsed'])) {
      $bank_account_data = json_decode($params['data_parsed'], TRUE);
      if (empty($bank_account_data)) {
        $bank_account_data = array();
      }
    }

    // add extra attributes
    foreach (array('description', 'name', 'country') as $attribute) {
      if (!empty($params[$attribute])) {
        $bank_account_data[$attribute] = $params[$attribute];
      }
    }

    // special treatment for IBANs
    if ($params['reference_type'] == 'IBAN') {
      $bank_account_data['country'] = substr($params['reference'], 0, 2);
      if (!empty($params['bic'])) {
        $bank_account_data['bic'] = $params['bic'];
      }
    }

    // and then create!
    $new_bank_account = civicrm_api3('BankingAccount', 'create', array(
      'contact_id'  => $params['contact_id'],
      'description' => CRM_Utils_Array::value('description', $params),
      'data_parsed' => json_encode($bank_account_data)));

    $bank_account_reference = civicrm_api3('BankingAccountReference', 'create', array(
      'reference'         => $params['reference'],
      'reference_type_id' => $reference_type_id,
      'ba_id'             => $new_bank_account['id']));

    return civicrm_api3('BankingAccount', 'getsingle', array('id' => $new_bank_account['id']));
  }
}

/**
 * Get or create a bank account for a given contact
 * @return array (reference )
 * @access public
 */
function _civicrm_api3_banking_account_getorcreate_spec(&$params){
  $params['reference'] = array(
    'name'         => 'reference',
    'title'        => 'Bank Account Reference',
    'api.required' => 1,
    'description'  => 'The bank account reference',
    );
  $params['contact_id'] = array(
    'name'         => 'contact_id',
    'title'        => 'Contact ID',
    'api.required' => 1,
    'description'  => 'CiviCRM contact ID',
    );
  $params['reference_type'] = array(
    'name'         => 'reference_type',
    'title'        => 'Bank Account Reference type',
    'api.default'  => 'IBAN',
    'description'  => 'The reference type (e.g. IBAN). This can be the name or id of the OptionValue',
    );
  $params['bic'] = array(
    'name'         => 'bic',
    'title'        => 'BIC',
    'api.required' => 0,
    'description'  => 'Only processed along with IBAN reference types',
    );
  $params['description'] = array(
    'name'         => 'description',
    'title'        => 'Account Description',
    'api.required' => 0,
    'description'  => 'If a new account is created this will be set as a description',
    );
  $params['name'] = array(
    'name'         => 'name',
    'title'        => 'Account Name',
    'api.required' => 0,
    'description'  => 'If a new account is created this will be set as a name',
    );
  $params['country'] = array(
    'name'         => 'country',
    'title'        => 'Country (two-letter)',
    'api.required' => 0,
    'description'  => 'If a new account is created this will be set as a country',
    );
}
