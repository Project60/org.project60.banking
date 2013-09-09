<?php

/**
 * Class contains functions for CiviBanking bank account references
 * 
 * Bank accounts in themselvs do not have a preferential external 'name'. They
 * can however have several different identifiers, e.g. IBAN, BIC and BBAN, or 
 * bank id, bank account id, branch id, .. depending on the way the banking 
 * system works in a particular country.
 * 
 * Note that this technique also allows 'tagging' of bank accounts by defining
 * your own 'reference types'. For instance, you van designate internal banka
 * accounts by giving them the reference 'purpose' => 'internal', etc.
 * 
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
   * Access this bank account reference's bank account, instantiating it if it 
   * does not yet exist
   * 
   * @return CRM_Banking_BAO_BankAccount or null
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

  /**
   * Format a bank reference of this type for display purposes
   *   e.g. format('iban','BE99999999999999') should return 'BE99 9999 9999 9999'
   *        format('bban','979367954852' should return '979-3679548-52'
   * Format functions should be defined as civicrm_banking_format_MYTYPE($value)
   * 
   * @param string $reference_type
   * @param string $value
   * @return string
   */
  public static function format($reference_type, $value) {
    $fn = 'civicrm_banking_format_' . $reference_type;
    if (function_exists($fn))
      return $fn($value);
    return $value;
  }

}

