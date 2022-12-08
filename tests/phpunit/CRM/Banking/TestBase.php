<?php

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
    return $plugin_bao->id;
  }
}
