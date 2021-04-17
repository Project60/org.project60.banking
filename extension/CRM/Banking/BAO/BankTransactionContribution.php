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

use CRM_Banking_ExtensionUtil as E;
/**
 * Class contains functions for CiviBanking plugin instances
 */
class CRM_Banking_BAO_BankTransactionContribution extends CRM_Banking_DAO_BankTransactionContribution {

  /**
   * @param array  $params
   *  (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Banking_DAO_BankTransactionContribution object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransactionContribution', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankTransactionContribution();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransactionContribution', $dao->id, $dao);
    return $dao;
  }

  /**
   * Link the given contribution to the given bank transaction
   *
   * @param integer $bank_tx_id
   * @param integer $contribution_id
   */
  public static function link($bank_tx_id, $contribution_id)
  {
    // don't check whether already linked, let the DB do that...
    CRM_Core_DAO::executeQuery(
        "INSERT IGNORE INTO civicrm_bank_tx_contribution (bank_tx_id,contribution_id) VALUES (%1, %2);",
        [
          1 => [$bank_tx_id, 'Integer'],
          2 => [$contribution_id, 'Integer'],
        ]
    );
  }
}

