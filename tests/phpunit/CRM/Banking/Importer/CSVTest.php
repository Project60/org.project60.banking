<?php

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
    $importer_spec = '/tests/resources/importer/configuration/Test01.civbanking';
    $importer_id = $this->configureCiviBankingModule(E::path($importer_spec));

    // import the file
    $import_file = '/tests/resources/importer/data/Test01.csv';
    $batch_id = $this->importFile($importer_id, E::path($import_file));
    $this->assertNotEmpty($batch_id, "Importer failed to read '{$import_file}'.");

    // check the content
    // todo
    
  }
}
