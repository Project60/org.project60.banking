<?php
/*
+--------------------------------------------------------------------+
| CiviCRM version 4.3                                                |
+--------------------------------------------------------------------+
| Copyright CiviCRM LLC (c) 2004-2013                                |
+--------------------------------------------------------------------+
| This file is a part of CiviCRM.                                    |
|                                                                    |
| CiviCRM is free software; you can copy, modify, and distribute it  |
| under the terms of the GNU Affero General Public License           |
| Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
|                                                                    |
| CiviCRM is distributed in the hope that it will be useful, but     |
| WITHOUT ANY WARRANTY; without even the implied warranty of         |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
| See the GNU Affero General Public License for more details.        |
|                                                                    |
| You should have received a copy of the GNU Affero General Public   |
| License and the CiviCRM Licensing Exception along                  |
| with this program; if not, contact CiviCRM LLC                     |
| at info[AT]civicrm[DOT]org. If you have questions about the        |
| GNU Affero General Public License or the licensing of CiviCRM,     |
| see the CiviCRM license FAQ at http://civicrm.org/licensing        |
+--------------------------------------------------------------------+
*/
/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
require_once 'CRM/Core/DAO.php';
require_once 'CRM/Utils/Type.php';
class CRM_Banking_DAO_BankTransactionBatch extends CRM_Core_DAO
{
  /**
   * static instance to hold the table name
   *
   * @var string
   * @static
   */
  static $_tableName = 'civicrm_bank_tx_batch';
  /**
   * static instance to hold the field values
   *
   * @var array
   * @static
   */
  static $_fields = null;
  /**
   * static instance to hold the FK relationships
   *
   * @var string
   * @static
   */
  static $_links = null;
  /**
   * static instance to hold the values that can
   * be imported
   *
   * @var array
   * @static
   */
  static $_import = null;
  /**
   * static instance to hold the values that can
   * be exported
   *
   * @var array
   * @static
   */
  static $_export = null;
  /**
   * static value to see if we should log any modifications to
   * this table in the civicrm_log table
   *
   * @var boolean
   * @static
   */
  static $_log = true;
  /**
   * ID
   *
   * @var int unsigned
   */
  public $id;
  /**
   * When the statement was issued
   *
   * @var datetime
   */
  public $issue_date;
  /**
   * The unique reference for this statement
   *
   * @var string
   */
  public $reference;
  /**
   * Used to maintain ordering and consistency
   *
   * @var int
   */
  public $sequence;
  /**
   *
   * @var float
   */
  public $starting_balance;
  /**
   *
   * @var float
   */
  public $ending_balance;
  /**
   * Currency
   *
   * @var string
   */
  public $currency;
  /**
   *
   * @var int
   */
  public $tx_count;
  /**
   * Start date of the statement period
   *
   * @var datetime
   */
  public $starting_date;
  /**
   * End date of the statement period
   *
   * @var datetime
   */
  public $ending_date;
  /**
   * class constructor
   *
   * @access public
   * @return civicrm_bank_tx_batch
   */
  function __construct()
  {
    $this->__table = 'civicrm_bank_tx_batch';
    parent::__construct();
  }
  /**
   * returns all the column names of this table
   *
   * @access public
   * @return array
   */
  static function &fields()
  {
    if (!(self::$_fields)) {
      self::$_fields = array(
        'id' => array(
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'required' => true,
          'export' => true,
          'where' => 'civicrm_bank_tx_batch.id',
          'headerPattern' => '',
          'dataPattern' => '',
        ) ,
        'issue_date' => array(
          'name' => 'issue_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => ts('Issue date') ,
          'required' => true,
        ) ,
        'reference' => array(
          'name' => 'reference',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Statement Reference') ,
          'required' => true,
          'maxlength' => 64,
          'size' => CRM_Utils_Type::BIG,
          'export' => true,
          'where' => 'civicrm_bank_tx_batch.reference',
          'headerPattern' => '',
          'dataPattern' => '',
        ) ,
        'sequence' => array(
          'name' => 'sequence',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Bank Statement sequence') ,
          'required' => true,
        ) ,
        'starting_balance' => array(
          'name' => 'starting_balance',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => ts('Starting Balance') ,
        ) ,
        'ending_balance' => array(
          'name' => 'ending_balance',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => ts('Ending Balance') ,
        ) ,
        'currency' => array(
          'name' => 'currency',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Currency') ,
          'maxlength' => 3,
          'size' => CRM_Utils_Type::FOUR,
        ) ,
        'tx_count' => array(
          'name' => 'tx_count',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Transaction Count') ,
          'required' => true,
        ) ,
        'starting_date' => array(
          'name' => 'starting_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => ts('Start date') ,
        ) ,
        'ending_date' => array(
          'name' => 'ending_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => ts('End date') ,
        ) ,
      );
    }
    return self::$_fields;
  }
  /**
   * returns the names of this table
   *
   * @access public
   * @static
   * @return string
   */
  static function getTableName()
  {
    return self::$_tableName;
  }
  /**
   * returns if this table needs to be logged
   *
   * @access public
   * @return boolean
   */
  function getLog()
  {
    return self::$_log;
  }
  /**
   * returns the list of fields that can be imported
   *
   * @access public
   * return array
   * @static
   */
  static function &import($prefix = false)
  {
    if (!(self::$_import)) {
      self::$_import = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('import', $field)) {
          if ($prefix) {
            self::$_import['bank_tx_batch'] = & $fields[$name];
          } else {
            self::$_import[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_import;
  }
  /**
   * returns the list of fields that can be exported
   *
   * @access public
   * return array
   * @static
   */
  static function &export($prefix = false)
  {
    if (!(self::$_export)) {
      self::$_export = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('export', $field)) {
          if ($prefix) {
            self::$_export['bank_tx_batch'] = & $fields[$name];
          } else {
            self::$_export[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_export;
  }
}
