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

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Banking_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
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
    $data = file_get_contents($configuration_file);
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
  public function importFile($importer_id, $input_file) : int
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
  public function getLatestTransactionBatchId() : int
  {
    return (int) CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_bank_tx_batch");
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
    $full_path = E::path($importer_spec);
    $this->assertTrue(file_exists($full_path), "Test resource '{$internal_path}' not found.");
    $this->assertTrue(is_readable($full_path), "Test resource '{$internal_path}' cannot be opened.");
    return $full_path;
  }
}
