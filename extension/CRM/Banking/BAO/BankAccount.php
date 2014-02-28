<?php

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
}

