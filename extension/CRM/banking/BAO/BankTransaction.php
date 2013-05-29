<?php

/**
 * Class contains functions for CiviBanking bank transactions
 */
class CRM_Banking_BAO_BankTransaction extends CRM_Banking_DAO_BankTransaction {

  protected $suggestion_objects = array();

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankTransaction object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    // add default dates
    if (!isset($params['value_date']))
      $params['value_date'] = date('YmdHis');
    if (!isset($params['booking_date']))
      $params['booking_date'] = date('YmdHis');

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransaction', CRM_Utils_Array::value('id', $params), $params);

    // TODO: convert the arrays (suggestions, data_parsed) back into JSON
    $dao = new CRM_Banking_DAO_BankTransaction();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransaction', $dao->id, $dao);
    return $dao;
  }

  /**
   * TODO: after a load/retrieve, need to convert the suggestions/data_parsed from JSON to array
   */  
  public function getSuggestions() {
    return $this->suggestion_objects;
  }

public function resetSuggestions() {
    $this->suggestion_objects = array();
  }

  public function addSuggestion( $suggestion ) {
    print_r($suggestion);
    $this->suggestion_objects[ $suggestion->getProbability() ][] = $suggestion;
  }

  public function saveSuggestions() {
    // TODO: discover how to save ONLY this field
  }

}

