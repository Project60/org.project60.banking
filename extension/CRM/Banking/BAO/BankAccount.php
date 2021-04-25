<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
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
 * Class contains functions for CiviBanking bank accounts
 */
class CRM_Banking_BAO_BankAccount extends CRM_Banking_DAO_BankAccount {

  /**
   * caches a decoded version of the data_parsed field
   */
  protected $_decoded_data_parsed = NULL;

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankAccount object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    // default values
    if (empty($params['id'])) {
      $params['created_date'] = date('YmdHis');
      $params['modified_date'] = date('YmdHis');
    } else {
      $params['modified_date'] = date('YmdHis');
    }

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankAccount', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankAccount();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankAccount', $dao->id, $dao);
    return $dao;
  }

  /**
   * Delete function override: also delete references
   */
  static function del($ba_id) {
    // delete all references...
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_bank_account_reference WHERE ba_id='$ba_id';");

    // ...then delete the bank account object
    $printLabel = new CRM_Banking_DAO_BankAccount();
    $printLabel->id = $ba_id;
    $printLabel->delete();
  }

  /**
   * will provide a cached version of the decoded data_parsed field
   * if $update=true is given, it will be parsed again
   */
  public function getDataParsed($update=false) {
    if ($this->_decoded_data_parsed==NULL || $update) {
      $this->_decoded_data_parsed = json_decode($this->data_parsed, true);
    }
    return $this->_decoded_data_parsed;
  }

  /**
   * store a data parsed structure into the db field.
   */
  public function setDataParsed($data) {
    $this->data_parsed = json_encode($data);
    $this->getDataParsed(true);
  }

  /**
   * @return a list of bank_reference arrays for this bank account,
   *          ordered by the reference types' order in the option group
   */
  public function getReferences() {
    $bank_account_reference_matching_probability = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_matching_probability');
    if ($bank_account_reference_matching_probability === null) {
      $bank_account_reference_matching_probability = 1.0;
    }

    $orderedReferences = array();
    $sql = "SELECT
                civicrm_option_value.value               AS reference_type,
                civicrm_option_value.label               AS reference_type_label,
                civicrm_option_value.description         AS reference_type_description,
                civicrm_option_value.id                  AS reference_type_id,
                civicrm_bank_account_reference.reference AS reference,
                civicrm_bank_account_reference.id        AS reference_id,
                civicrm_bank_account.contact_id          AS contact_id,
                civicrm_contact.is_deleted               AS is_deleted,
                civicrm_contact.is_deceased              AS is_deceased
            FROM civicrm_bank_account_reference
            LEFT JOIN civicrm_option_value ON civicrm_bank_account_reference.reference_type_id = civicrm_option_value.id
            LEFT JOIN civicrm_bank_account ON civicrm_bank_account_reference.ba_id = civicrm_bank_account.id
            LEFT JOIN civicrm_contact      ON civicrm_contact.id = civicrm_bank_account.contact_id
            WHERE civicrm_bank_account_reference.ba_id = {$this->id}
            ORDER BY civicrm_option_value.weight ASC;";
    $orderedReferenceQuery = CRM_Core_DAO::executeQuery($sql);
    while ($orderedReferenceQuery->fetch()) {
      $orderedReferences[] = array(  'reference_type'             => $orderedReferenceQuery->reference_type,
                                     'reference_type_label'       => $orderedReferenceQuery->reference_type_label,
                                     'reference_type_description' => $orderedReferenceQuery->reference_type_description,
                                     'reference_type_id'          => $orderedReferenceQuery->reference_type_id,
                                     'reference'                  => $orderedReferenceQuery->reference,
                                     'id'                         => $orderedReferenceQuery->reference_id,
                                     'contact_id'                 => $orderedReferenceQuery->contact_id,
                                     'contact_ok'                 => ((!empty($orderedReferenceQuery->contact_id))
                                                                     && empty($orderedReferenceQuery->is_deleted)
                                                                     && empty($orderedReferenceQuery->is_deceased)) ? '1' : '0',
                                    'probability'                 => $bank_account_reference_matching_probability,
          );
    }
    return $orderedReferences;
  }
}

