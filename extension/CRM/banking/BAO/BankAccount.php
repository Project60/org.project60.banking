<?php

/**
 * Class contains functions for CiviBanking bank accounts
 */
class CRM_Banking_BAO_BankAccount extends CRM_Banking_DAO_BankAccount {

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

}

