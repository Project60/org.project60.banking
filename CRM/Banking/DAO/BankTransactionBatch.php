<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.project60.banking/xml/schema/CRM/Banking/BankTransactionBatch.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:0bbb3196810c4399aaec4a31ab6c3b0d)
 */
use CRM_Banking_ExtensionUtil as E;

/**
 * Database access object for the BankTransactionBatch entity.
 */
class CRM_Banking_DAO_BankTransactionBatch extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '4.3';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_bank_tx_batch';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * When the statement was issued
   *
   * @var string
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $issue_date;

  /**
   * The unique reference for this statement
   *
   * @var string
   *   (SQL type: varchar(64))
   *   Note that values will be retrieved from the database as a string.
   */
  public $reference;

  /**
   * Used to maintain ordering and consistency
   *
   * @var int|string
   *   (SQL type: int)
   *   Note that values will be retrieved from the database as a string.
   */
  public $sequence;

  /**
   * @var float|string|null
   *   (SQL type: decimal(20,2))
   *   Note that values will be retrieved from the database as a string.
   */
  public $starting_balance;

  /**
   * @var float|string|null
   *   (SQL type: decimal(20,2))
   *   Note that values will be retrieved from the database as a string.
   */
  public $ending_balance;

  /**
   * Currency
   *
   * @var string|null
   *   (SQL type: varchar(3))
   *   Note that values will be retrieved from the database as a string.
   */
  public $currency;

  /**
   * @var int|string
   *   (SQL type: int)
   *   Note that values will be retrieved from the database as a string.
   */
  public $tx_count;

  /**
   * Start date of the statement period
   *
   * @var string|null
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $starting_date;

  /**
   * End date of the statement period
   *
   * @var string|null
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $ending_date;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_bank_tx_batch';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Bank Transaction Batches') : E::ts('Bank Transaction Batch');
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('ID'),
          'description' => E::ts('ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => TRUE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.id',
          'export' => TRUE,
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => '4.3',
        ],
        'issue_date' => [
          'name' => 'issue_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Issue date'),
          'description' => E::ts('When the statement was issued'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.issue_date',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'reference' => [
          'name' => 'reference',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Statement Reference'),
          'description' => E::ts('The unique reference for this statement'),
          'required' => TRUE,
          'maxlength' => 64,
          'size' => CRM_Utils_Type::BIG,
          'usage' => [
            'import' => FALSE,
            'export' => TRUE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.reference',
          'export' => TRUE,
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'sequence' => [
          'name' => 'sequence',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Bank Statement sequence'),
          'description' => E::ts('Used to maintain ordering and consistency'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.sequence',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'starting_balance' => [
          'name' => 'starting_balance',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('Starting Balance'),
          'precision' => [
            20,
            2,
          ],
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.starting_balance',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'ending_balance' => [
          'name' => 'ending_balance',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('Ending Balance'),
          'precision' => [
            20,
            2,
          ],
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.ending_balance',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'currency' => [
          'name' => 'currency',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Currency'),
          'description' => E::ts('Currency'),
          'maxlength' => 3,
          'size' => CRM_Utils_Type::FOUR,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.currency',
          'dataPattern' => '/^[A-Z]{3}$/i',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'tx_count' => [
          'name' => 'tx_count',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Transaction Count'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.tx_count',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'starting_date' => [
          'name' => 'starting_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Start date'),
          'description' => E::ts('Start date of the statement period'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.starting_date',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
        'ending_date' => [
          'name' => 'ending_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('End date'),
          'description' => E::ts('End date of the statement period'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_bank_tx_batch.ending_date',
          'table_name' => 'civicrm_bank_tx_batch',
          'entity' => 'BankTransactionBatch',
          'bao' => 'CRM_Banking_DAO_BankTransactionBatch',
          'localizable' => 0,
          'add' => '4.3',
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'bank_tx_batch', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'bank_tx_batch', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'reference' => [
        'name' => 'reference',
        'field' => [
          0 => 'reference',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_bank_tx_batch::1::reference',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
