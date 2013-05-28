<?php
/**
 * Class contains functions for CiviBanking bank transactions
 */
class CRM_Banking_BAO_BankTransaction extends CRM_Banking_DAO_BankTransaction {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankTransaction object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    // add default dates
    if (!isset($params['value_date']))    $params['value_date'] = date('YmdHis');
    if (!isset($params['booking_date']))  $params['booking_date'] = date('YmdHis');

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransaction', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankTransaction();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransaction', $dao->id, $dao);
    return $dao;
  }

}

