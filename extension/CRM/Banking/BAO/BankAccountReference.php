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
   * @deprecated
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

  /**
   * Normalise a reference (if a normalisation is available)
   *
   * @param $reference_type_name the name of the type, e.g. IBAN, NBAN_DE, ...
   *
   * @return  FALSE if no normalisation is possible (not implemented)
   *          0     if doesn't comply with standard
   *          1     if reference is already normalised
   *          2     if reference was normalised
   */
  public static function normalise($reference_type_name, &$reference) {
    $match = array();
    switch ($reference_type_name) {
      case 'IBAN':
        $structure_correct = self::std_normalisation($reference_type_name, $reference,
          "#^(?P<IBAN>[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16})$#", "%s");
        if (!$structure_correct) {
          return $structure_correct;
        } else {
          // structure correct, check the checksum...
          if ((TRUE == include('packages/php-iban-1.4.0/php-iban.php'))
                 && function_exists('verify_iban')) {
            if (verify_iban($reference)) {
              return $structure_correct;
            } else {
              return 0;
            }
          } else {
            // this means we cannot check beyond structural compliance...
            //   ...but what can we do?
            return $structure_correct;
          }
        }
        return FALSE; // we shouldn't get here

      case 'NBAN_DE':
        return self::std_normalisation($reference_type_name, $reference,
          "#^(?P<BLZ>\\d{8})/(?P<KTO>\\d{2,10})$#", "%08d/%010d");

      case 'NBAN_CH':
        return self::std_normalisation($reference_type_name, $reference,
          "#^(?P<PRE>\\d{1,2})-(?P<KTO>\\d{2,9})-(?P<SUF>\\d{1})$#", "%02d-%09d-%01d");

      case 'NBAN_CZ':
        // first, try with prefix
        $result = self::std_normalisation($reference_type_name, $reference,
          "#^(?P<PREFIX>\\d{1,6})-(?P<ACCT>\\d{1,10})/(?P<BANK>\\d{1,4})$#", "%06d-%010d/%04d");
        if ($result) {
          return $result;
        } else {
          // if failed, try with shortened form (no prefix)
          return self::std_normalisation($reference_type_name, $reference,
            "#^(?P<ACCT>\\d{1,10})/(?P<BANK>\\d{1,4})$#", "%010d/%04d");
        }

      default:
        // not implemented
        return FALSE;
    }
  }

  /**
   * helper function for normalised strings
   */
  protected static function std_normalisation($reference_type_name, &$reference, $pattern, $format) {
    // first convert to upper case and strip whitespaces
    $normalised_reference = strtoupper($reference);
    $normalised_reference = preg_replace('#\\s#', '', $normalised_reference);
    // error_log("Filtered: $normalised_reference");

    if (preg_match($pattern, $normalised_reference, $match)) {
      $normalised_reference = sprintf($format, CRM_Utils_Array::value(1, $match), CRM_Utils_Array::value(2, $match), CRM_Utils_Array::value(3, $match), CRM_Utils_Array::value(4, $match), CRM_Utils_Array::value(5, $match));
      // error_log("Normalised: $normalised_reference");
      if ($reference===$normalised_reference) {
        return 1;
      } else {
        $reference = $normalised_reference;
        return 2;
      }
    } else {
      return 0;
    }
  }

}
