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
 * Class contains functions for CiviBanking bank transactions
 */
class CRM_Banking_BAO_BankTransactionBatch extends CRM_Banking_DAO_BankTransactionBatch {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankTransaction object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    // add default dates
    if (!isset($params['issue_date']))
      $params['issue_date'] = date('YmdHis');
    if (!isset($params['reference']))
      $params['reference'] = microtime();
    if (!isset($params['sequence']))
      $params['sequence'] = 0;
    if (!isset($params['tx_count']))
      $params['tx_count'] = 0;

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransactionBatch', CRM_Utils_Array::value('id', $params), $params);

    // TODO: convert the arrays (suggestions, data_parsed) back into JSON
    $dao = new CRM_Banking_DAO_BankTransactionBatch();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransactionBatch', $dao->id, $dao);
    return $dao;
  }

}
