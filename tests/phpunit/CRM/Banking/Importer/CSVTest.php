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
 * Simple CSV Importer tests
 *
 * @group headless
 */
class CRM_Banking_Importer_CSVTest extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
    return parent::setUpHeadless();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
    parent::tearDown();
  }

  /**
   * Test a basic PayPal import
   */
  public function testBasicCSVImport():void {
    // create importer
    $importer_id = $this->configureCiviBankingModule(
      $this->getTestResourcePath('importer/configuration/Test01.civbanking'));

    // import the file
    $import_file = $this->getTestResourcePath('importer/data/Test01.csv');
    $batch_id = $this->importFile($importer_id, $import_file);
    $batch = $this->getBatch($batch_id);
    $this->assertNotEmpty($batch, "Importer failed to read '{$import_file}'.");

    // load the transactions
    $transactions = $batch->getTransactions();
    $this->assertEquals(1, count($transactions), "Unexpected amount of transactions received");

    /** @var $trxn CRM_Banking_BAO_BankTransaction */
    $trxn = reset($transactions);
    $this->assertEquals(84.00, $trxn['amount'], "The amount is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['value_date'])), '2022-10-07', "The value date is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['booking_date'])), '2022-10-07', "The booking date is off.");
  }

  /**
   * Test a CSV import with a BOM
   */
  public function testBasicCSVImportWithBOM():void {
    // create importer
    $importer_id = $this->configureCiviBankingModule(
      $this->getTestResourcePath('importer/configuration/Test02.civbanking'));

    // import the file
    $import_file = $this->getTestResourcePath('importer/data/Test02_UTF8_with_BOM.csv');
    $batch_id = $this->importFile($importer_id, $import_file);
    $this->assertNotEmpty($batch_id, "Import failed");
    $batch = $this->getBatch($batch_id);
    $this->assertNotEmpty($batch, "Importer failed to read '{$import_file}'.");

    // load the transactions
    $transactions = $batch->getTransactions();
    $this->assertEquals(1, count($transactions), "Unexpected amount of transactions received");

    /** @var $trxn CRM_Banking_BAO_BankTransaction */
    $trxn = reset($transactions);
    $this->assertEquals(-9.99, $trxn['amount'], "The amount is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['value_date'])), '2022-06-07', "The value date is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['booking_date'])), '2022-06-07', "The booking date is off.");


    // also: check the file without BOM
    $import_file = $this->getTestResourcePath('importer/data/Test02_UTF8_without_BOM.csv');
    $batch_id = $this->importFile($importer_id, $import_file);
    $this->assertNotEmpty($batch_id, "Import failed");
    $batch = $this->getBatch($batch_id);
    $this->assertNotEmpty($batch, "Importer failed to read '{$import_file}'.");

    // load the transactions
    $transactions = $batch->getTransactions();
    $this->assertEquals(1, count($transactions), "Unexpected amount of transactions received");

    /** @var $trxn CRM_Banking_BAO_BankTransaction */
    $trxn = reset($transactions);
    $this->assertEquals(-9.99, $trxn['amount'], "The amount is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['value_date'])), '2022-06-07', "The value date is off.");
    $this->assertEquals(date('Y-m-d', strtotime($trxn['booking_date'])), '2022-06-07', "The booking date is off.");

  }
}
