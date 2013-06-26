<?php

/**
 * Class contains functions for CiviBanking bank accounts
 */
class CRM_Banking_BAO_BankAccountReference extends CRM_Banking_DAO_BankAccountReference {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankAccount object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankAccountReference', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankAccountReference();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankAccountReference', $dao->id, $dao);
    return $dao;
  }

  /**
   * 
   * 
   */
  function getBankAccount() {
    if ($this->ba_id) {
      $bank_bao = new CRM_Banking_BAO_BankAccount();
      $bank_bao->get('id', $this->ba_id);
      return $bank_bao;
    } else {
      return NULL;
    }
  }
}

