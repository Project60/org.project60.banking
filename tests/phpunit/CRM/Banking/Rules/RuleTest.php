<?php
// @todo test setters, save, get...
define('DRUPAL_ROOT', getcwd());
chdir(DRUPAL_ROOT); // This seems to be required.
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET'; // without this I've had crashes

// Bootstrap Drupal.
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

/**
 *
 * Tests that test the functionality without writing to the database.
 *
 * @group no_db_writes
 */
class CRM_Banking_Rules_RuleTest extends \PHPUnit_Framework_TestCase {
  public function testDatabaseArrayCasting() {
    civicrm_initialize();
    $x = new CRM_Banking_Rules_Rule();
    $x->setFromArray([
      'conditions' => serialize(['foo' => 'bar']),
      'execution' => serialize(['bax' => 'bim']),
    ]);
    $this->assertEquals(['foo' => 'bar'], $x->getConditions());
    $this->assertEquals(['bax' => 'bim'], $x->getExecution());

    $x = new CRM_Banking_Rules_Rule();
    $x->setFromArray([
      'conditions' => ['foo' => 'bar'],
      'execution' => ['bax' => 'bim'],
    ], FALSE);
    $this->assertEquals(['foo' => 'bar'], $x->getConditions());
    $this->assertEquals(['bax' => 'bim'], $x->getExecution());
  }
  public function testCasting() {
    civicrm_initialize();
    $x = new CRM_Banking_Rules_Rule();

    // Simplest test.
    $this->assertEquals('foo', $x->setTx_reference('foo')->getTx_reference());

    // Test casting.
    $this->assertEquals(12.35, $x->setAmount_min(12.35)->getAmount_min());
    $this->assertEquals(0, $x->setAmount_min('twelve')->getAmount_min());
    $this->assertEquals(date('c'), $x->setLast_match('now')->getLast_match());
    $this->assertEquals(1, $x->setIs_enabled(TRUE)->getIs_enabled());
    $this->assertEquals(1, $x->setIs_enabled(-1)->getIs_enabled());
    $this->assertEquals(0, $x->setIs_enabled('')->getIs_enabled());

    // NULLs
    $this->assertNull($x->setAmount_min(NULL)->getAmount_min());
  }
  public function testCastingNull() {
    civicrm_initialize();
    $x = new CRM_Banking_Rules_Rule();
    $this->assertNull($x->setAmount_min(NULL)->getAmount_min());
  }
  public function testInsert() {
    $rule = new CRM_Banking_Rules_Rule();
    $test = $this;

    // Mock the database and ensure an INSERT SQL statement happens.
    $this->mock($rule, 'db_execute_method', [
      function($sql, $params) use ($test) {
        $test->assertStringStartsWith('INSERT', $sql);
        return NULL;
      }
    ]);
    // Mock the get last insert id and return 123.
    $this->mock($rule, 'db_single_value_query_method', [
      function($sql, $params) use ($test) { return 123; }
    ]);

    $rule->setName('rule test')
      ->setTx_reference('some reference')
      ->save();
    $this->assertEquals(123, $rule->getId());

    return $rule;
  }
  /**
   * @depends testInsert
   */
  public function testUpdate($rule) {
    // Mock the database and ensure an UPDATE SQL statement happens.
    $test = $this;
    $this->mock($rule, 'db_execute_method', [
      function($sql, $params) use ($test) {
        $test->assertStringStartsWith('UPDATE', $sql);
        return NULL;
      }
    ]);

    $rule->setName('a different name')
      ->save();
  }
  /**
   * @param CRM_Banking_Rules_Rule $rule
   * @param string $method either db_execute_method or db_single_value_query_method
   * @param array $handlers. Array of callbacks that mock the behaviour.
   * @return $this.
   */
  protected function mock($rule, $method, $handlers) {
    $rule->$method = function() use (&$handlers) {
      if (count($handlers) == 0) {
        throw new \Exception("executeQuery called more times than given handlers values.");
      }
      $handler = array_shift($handlers);
      return call_user_func_array($handler, func_get_args());
    };
    return $this;
  }
}
