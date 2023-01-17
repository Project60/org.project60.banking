<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking - Unit Test                   |
| Copyright (C) 2022 SYSTOPIA                            |
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

use CRM_Banking_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

include_once "CRM/Banking/Helpers/OptionValue.php";

/**
 * Base class for all CiviBanking tests
 *
 * @group headless
 */
class CRM_Banking_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface
{
  use \Civi\Test\Api3TestTrait {
    callAPISuccess as protected traitCallAPISuccess;
  }

  /** The primary fields of the transaction are the fields of its database table. All other fields will be written as JSON to "data_parsed".*/
  const PRIMARY_TRANSACTION_FIELDS = ['version', 'debug', 'amount', 'bank_reference', 'value_date', 'booking_date', 'currency', 'type_id', 'status_id', 'data_raw', 'data_parsed', 'ba_id', 'party_ba_id', 'tx_batch_id', 'sequence'];

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder
  {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void
  {
    parent::setUp();
  }

  public function tearDown(): void
  {
    parent::tearDown();
  }

  /**
   * Import a new banking module based on a .civibanking configuration file
   *
   * @param string $configuration_file
   *   either a configuration file path
   *
   * @return integer
   *    module ID
   */
  public function configureCiviBankingModule($configuration_file)
  {
    $this->assertTrue(file_exists($configuration_file), "Configuration file '{$configuration_file}' not found.");
    $this->assertTrue(is_readable($configuration_file), "Configuration file '{$configuration_file}' cannot be opened.");
    $data          = file_get_contents($configuration_file);
    $decoding_test = json_decode($data, true);
    $this->assertTrue(is_array($decoding_test), "Configuration file '{$configuration_file}' didn't contain json.");
    $plugin_bao = new CRM_Banking_BAO_PluginInstance();
    $plugin_bao->updateWithSerialisedData($data);
    $this->assertNotEmpty($plugin_bao->id, "Configuration from file '{$configuration_file}' couldn't be stored.");
    return $plugin_bao->id;
  }

  /**
   * Import bank statement file
   *
   * @param integer $importer_id
   *   importer ID
   *
   * @param string $input_file
   *   file path to the file to be imported
   *
   * @return integer
   *    tx_batch ID
   */
  public function importFile($importer_id, $input_file): int
  {
    $this->assertTrue(file_exists($input_file), "Configuration file '{$input_file}' not found.");
    $this->assertTrue(is_readable($input_file), "Configuration file '{$input_file}' cannot be opened.");

    // load the [first, hopefully only] Matcher of this plugin class type and get its config.
    /** @var CRM_Banking_PluginModel_Importer $importer */
    $importer = $this->getPluginInstance($importer_id);
    $importer->import_file($input_file, ['source' => $input_file]);
    $batch_id = $this->getLatestTransactionBatchId();
    $this->assertNotEmpty($batch_id, "Importer module [{$importer_id}] failed on file '{$input_file}'.");
    return $batch_id;
  }

  /**
   * Get the ID of the latest transaction batch
   *
   * @return integer
   *   the ID of the latest batch
   */
  public function getLatestTransactionId(): int
  {
    return (int)CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_bank_tx");
  }

  /**
   * Get an instance of the latest bank transaction
   *
   * @return CRM_Banking_BAO_BankTransaction|null
   *   the ID of the latest batch
   */
  public function getLatestTransaction()
  {
    $latest_tx_id = $this->getLatestTransactionId();
    if ($latest_tx_id) {
      return $this->getTransactionInstance($latest_tx_id);
    } else {
      return null;
    }
  }

  /**
   * Get a transaction BAO
   *
   * @param integer $tx_id
   *   the ID of the transaction to be loaded. If left empty, the most recently created one is returned
   *
   * @return CRM_Banking_BAO_BankTransaction|null
   *   the transaction batch
   */
  public function getTransactionInstance(int $tx_id = 0)
  {
    $tx_bao = new CRM_Banking_BAO_BankTransaction();
    if ($tx_id) {
      $tx_bao->id = $tx_id;
    } else {
      $tx_bao->id = $this->getLatestTransactionId();
    }
    if ($tx_bao->find()) {
      $tx_bao->fetch();
      return $tx_bao;
    } else {
      return null;
    }
  }


  /**
   * Get the ID of the latest transaction batch
   *
   * @return integer
   *   the ID of the latest batch
   */
  public function getLatestTransactionBatchId(): int
  {
    return (int)CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_bank_tx_batch");
  }

  /**
   * Get a transaction batch BAO
   *
   * @param integer $batch_id
   *   the ID of the batch to be loaded. If left empty, the most recently created one is returned
   *
   * @return CRM_Banking_BAO_BankTransactionBatch|null
   *   the transaction batch
   */
  public function getBatch(int $batch_id = 0)
  {
    $batch_bao = new CRM_Banking_BAO_BankTransactionBatch();
    if ($batch_id) {
      $batch_bao->id = $batch_id;
    } else {
      $batch_bao->id = $this->getLatestTransactionBatchId();
    }
    if ($batch_bao->find()) {
      $batch_bao->fetch();
      return $batch_bao;
    } else {
      return null;
    }
  }

  /**
   * Get an instance of the plugin with the given ID
   *
   * @param integer $plugin_id
   *   the ID of the plugin
   *
   * @return CRM_Banking_PluginModel_Base
   *   plugin instance
   */
  public function getPluginInstance($plugin_id)
  {
    // load the Matcher and the mapping
    $pi_bao = new CRM_Banking_BAO_PluginInstance();
    $pi_bao->get('id', $plugin_id);
    return $pi_bao->getInstance();
  }

  /**
   * Get the full path of a test resource
   *
   * @param string $internal_path
   *   the internal path
   *
   * @return string
   *   the full path
   */
  public function getTestResourcePath($internal_path)
  {
    $importer_spec = '/tests/resources/' . $internal_path;
    $full_path     = E::path($importer_spec);
    $this->assertTrue(file_exists($full_path), "Test resource '{$internal_path}' not found.");
    $this->assertTrue(is_readable($full_path), "Test resource '{$internal_path}' cannot be opened.");
    return $full_path;
  }

  /**
   * Create a contact and return its ID.
   *
   * @param array $additional_parameters
   *    additional parameters for hte contact
   *
   * @return int
   *    The ID of the created contact.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  public function createContact($additional_parameters = []): int
  {
    $defaults = [
      'contact_type' => 'Individual',
      'email'        => 'unittests@banking.project60.org',
    ];
    $contact_parameters = array_merge($defaults, $additional_parameters);
    $contact = $this->callAPISuccess('Contact', 'create', $contact_parameters);
    $this->assertArrayHasKey('id', $contact, "Contact was not created.");
    $this->assertNotEmpty($contact['id'], "Contact was not created.");
    return $contact['id'];
  }

  /**
   * Get a transaction by its ID.
   *
   * @param int $id
   *   the transaction ID
   *
   * @return array
   *  transaction data
   */
  protected function getTransaction(int $id): array
  {
    $transaction = $this->callAPISuccess('BankingTransaction', 'getsingle', ['id' => $id]);
    unset($transaction['is_error']);
    return $transaction;
  }

  /**
   * Create a transaction and return its ID.
   *
   * @param array $parameters
   *   The parameters for the transaction. Only set values will overwrite defaults.
   *
   * @return int
   *   The ID of the created transaction.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  protected function createTransaction(array $parameters = []): int
  {
    static $transactionReferenceCounter = 0;
    $transactionReferenceCounter++;

    // create some default values
    $defaults = [
      'bank_reference' => 'TestBankReference-' . $transactionReferenceCounter,
      'booking_date'   => date('Y-m-d'),
      'value_date'     => date('Y-m-d'),
      'currency'       => 'EUR',
      'sequence'       => $transactionReferenceCounter,
      'status_id'      => $this->getTxStatusID('new'),
    ];

    // overwrite the values submitted
    $transaction = array_merge($defaults, $parameters);

    // Fill parsed data:
    $parsedData = [];
    foreach ($transaction as $key => $value) {
      if (!in_array($key, self::PRIMARY_TRANSACTION_FIELDS)) {
        $parsedData[$key] = $value;
        unset($transaction[$key]);
      }
    }
    $transaction['data_parsed'] = json_encode($parsedData);

    // create the transaction
    $result = $this->callAPISuccess('BankingTransaction', 'create', $transaction);
    return $result['id'];
  }

  /**
   * Get the latest contribution.
   *
   * @return array The contribution.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  protected function getLatestContribution()
  {
    try {
      return $this->callAPISuccessGetSingle(
          'Contribution',
          [
              'options' => [
                  'sort'  => 'id DESC',
                  'limit' => 1,
              ],
          ]
      );
    } catch (Exception $ex) {
      return null;
    }
  }

  /**
   * Create a matcher and return its ID.
   *
   * @param string $type The matcher/analyser type, e.g. "match".
   * @param string $class The matcher/analyser class, e.g. "analyser_regex".
   * @param string $configuration The configuration for the matcher. Only set values will overwrite defaults.
   * @param string $parameters The parameters for the matcher. Only set values will overwrite defaults.
   *
   * @return int The matcher ID.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  protected function createMatcher(
    string $type,
    string $class,
    array $configuration = [],
    array $parameters = []
  ): int {
    $typeId = $this->matcherTypeNameToId($type);
    $classId = $this->matcherClassNameToId($class);

    $parameterDefaults = [
      'plugin_class_id' => $classId,
      'plugin_type_id' => $typeId,
      'name' => 'Test Matcher ' . $type,
      'description' => 'Test Matcher "' . $type . '" with class "' . $class . '"',
      'enabled' => 1,
      'weight' => $this->getNextPluginWeight(),
      'state' => '{}',
    ];

    $mergedParameters = array_merge($parameterDefaults, $parameters);
    $matcher = $this->callAPISuccess('BankingPluginInstance', 'create', $mergedParameters);
    $configurationDefaults = ['auto_exec' => 1];
    $mergedConfiguration = array_merge($configurationDefaults, $configuration);

    // Set the config via SQL (API causes issues):
    if (empty($matcher['id'])) {
      throw new Exception("Matcher could not be created.");
    } else {
      $configurationAsJson = json_encode($mergedConfiguration);

      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_bank_plugin_instance SET config=%1 WHERE id=%2;",
        [
          1 => [$configurationAsJson, 'String'],
          2 => [$matcher['id'], 'Integer']
        ]
      );
    }

    return $matcher['id'];
  }

  /**
   * Return the ID for a type by its name.
   *
   * @param string $typeName
   *    The internal name of the type.
   *
   * @return int
   *    The ID of the type.
   */
  protected function matcherTypeNameToId(string $typeName): int
  {
    return $this->callAPISuccess('OptionValue', 'getsingle', [
        // NOTE: Class and type seem to be flipped in the extension code:
        'option_group_id' => 'civicrm_banking.plugin_classes',
        'name' => $typeName,
      ])['id'];
  }

  /**
   * Return the ID for a type by its name.
   *
   * @param string $className
   *    The internal name of the class.
   *
   * @return int
   *    The ID of the type.
   */
  protected function matcherClassNameToId(string $className): int
  {
    return $this->callAPISuccess('OptionValue', 'getsingle', [
      // NOTE: Class and type seem to be flipped in the extension code:
      'option_group_id' => 'civicrm_banking.plugin_types',
      'name' => $className,
    ])['id'];
  }

  /**
   * Get a weight value that is higher than all previously issued ones
   *
   * @return int
   *   the weight value
   */
  protected function getNextPluginWeight()
  {
    static $weight = 10;
    $weight += 10;
    return $weight;
  }

  /**
   * Process transactions, i.e. run all matchers on it. By default, all transactions are process
   *
   * @param array|null $transactionIds
   *  Will be used instead of all created transactions if not null.
   */
  public function runMatchers(array $transactionIds = null): void
  {
    $transactionIdsForMatching = $transactionIds === null ? $this->getAllTransactionIDs() : $transactionIds;
    $engine = new CRM_Banking_Matcher_Engine();
    foreach ($transactionIdsForMatching as $transactionId) {
      $engine->match($transactionId);
    }
  }

  /**
   * Run all transactions
   *
   * @param $status_ids
   *   filter by these status IDs. Default is 'new'
   *
   * @return array
   *   list of transaction IDs
   */
  public function getAllTransactionIDs($status_ids = null)
  {
    $transactions = [];
    if ($status_ids === null) {
      $status_new = $this->getTxStatusID('new');
      $status_ids = [$status_new];
    }

    $status_id_list = implode(",", $status_ids);
    $tx_search = CRM_Core_DAO::executeQuery("SELECT id AS tid FROM civicrm_bank_tx WHERE status_id IN ({$status_id_list})");
    while ($tx_search->fetch()) {
      $transactions[] = $tx_search->tid;
    }
    return $transactions;
  }

  /**
   * Get the status ID for the given status
   *
   * @param $status
   *   the status name, like 'new', 'suggestions', 'ignored' or 'processed'
   *
   * @return int
   */
  public function getTxStatusID($status)
  {
    static $status_list = [];
    if (!isset($status_list[$status])) {
      $status_entry = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', $status);
      $status_list[$status] = $status_entry;
    }
    return $status_list[$status];
  }

  /**
   * Get the data_parsed array from a transaction
   *
   * @param int $id
   *   transaction ID
   *
   * @return array
   *   (extracted) contents of data_parsed
   */
  protected function getTransactionDataParsed(int $id): array
  {
    $transaction = $this->getTransaction($id);
    $this->assertArrayHasKey('data_parsed', $transaction, 'No data_parsed set');
    $parsed_data = json_decode($transaction['data_parsed'], true);
    $this->assertNotNull($parsed_data, 'Invalid data_parsed blob');
    return $parsed_data;
  }

  // LEGACY FUNCTIONS

  /**
   * Create a "create contribution" matcher with simple defaults.
   *
   * @param array $configuration
   *  The configuration for the matcher. Only set values will overwrite defaults.
   *
   * @return int
   *   The matcher ID.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  protected function createCreateContributionMatcher(array $configuration = []): int
  {
    $defaultConfiguration = [
      'required_values' => [
        'btx.financial_type_id',
        'btx.payment_instrument_id',
        'btx.contact_id',
      ],
      'value_propagation' => [
        'btx.financial_type_id' => 'contribution.financial_type_id',
        'btx.payment_instrument_id' => 'contribution.payment_instrument_id',
      ],
      'lookup_contact_by_name' => [
        'mode' => 'off'
      ]
    ];
    $mergedConfiguration = array_merge($defaultConfiguration, $configuration);
    $matcherId = $this->createMatcher('match', 'matcher_create', $mergedConfiguration);
    return $matcherId;
  }

  /**
   * Create a regex analyser with simple defaults.
   *
   * @param array|null $rules
   *  The rules to apply for the matcher. If null, default rules are used, otherwise the given ones.
   *
   * @param array $configuration
   *   The configuration for the matcher. Only set values will overwrite defaults.
   *
   * @return int The matcher ID.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  public function createRegexAnalyser(array $rules = null, array $configuration = []): int
  {
    $defaultRules = [
      [
        'comment' => 'Austrian address type 1',
        'fields' => [
          'address_line'
        ],
        'pattern' => '#^(?P<postal_code>[0-9]{4}) (?P<city>[\\w\/]+)[ ,]*(?P<street_address>.*)$#',
        'actions' => [
          [
            'from' => 'street_address',
            'action' => 'copy',
            'to' => 'street_address'
          ],
          [
            'from' => 'postal_code',
            'action' => 'copy',
            'to' => 'postal_code'
          ],
          [
            'from' => 'city',
            'action' => 'copy',
            'to' => 'city'
          ]
        ]
      ],
      [
        'comment' => 'Austrian address type 2',
        'fields' => [
          'address_line'
        ],
        'pattern' => '#^(?P<street_address>[^,]+).*(?P<postal_code>[0-9]{4}) +(?P<city>[\\w ]+)$#',
        'actions' => [
          [
            'from' => 'street_address',
            'action' => 'copy',
            'to' => 'street_address'
          ],
          [
            'from' => 'postal_code',
            'action' => 'copy',
            'to' => 'postal_code'
          ],
          [
            'from' => 'city',
            'action' => 'copy',
            'to' => 'city'
          ]
        ]
      ]
    ];

    $finalRules = $rules === null ? $defaultRules : $rules;
    $defaultConfiguration = ['rules' => $finalRules];
    $mergedConfiguration = array_merge($defaultConfiguration, $configuration);
    return $this->createMatcher('match', 'analyser_regex', $mergedConfiguration);
  }

  /**
   * Generate a random string
   *
   * @return string
   *   a random string
   */
  public function getRandomString($length = 32)
  {
    return substr(base64_encode(random_bytes($length)), 0, $length);
  }

  /**
   * Get a random financial type ID
   *
   * @return int
   *   random (valid) financial type ID
   */
  public function getRandomFinancialTypeID()
  {
    return CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_financial_type ORDER BY RAND() LIMIT 1;");
  }


  /**
   * Get a random option value from the given group
   *
   * @param string|int
   *   $option_group_id
   *
   * @return string|integer
   *   random option value
   */
  public function getRandomOptionValue($option_group_id)
  {
    $this->assertNotEmpty($option_group_id, "No option group ID/name given");
    if (!is_numeric($option_group_id)) {
      $option_group_id = (int) CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_option_group WHERE name = %1", [1 => [$option_group_id, 'String']]);
      $this->assertNotEmpty($option_group_id, "Unknown option group");
    }
    return CRM_Core_DAO::singleValueQuery(
      "SELECT value FROM civicrm_option_value WHERE option_group_id = %1 ORDER BY RAND() LIMIT 1;",
      [1 => [$option_group_id, 'String']]);
  }
}
