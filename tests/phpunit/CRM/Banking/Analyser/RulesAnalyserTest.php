<?php

/*-------------------------------------------------------+
| Project 60 - CiviBanking - PHPUnit tests               |
| Copyright (C) 2023 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
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
 * Tests for the CreateContribution class.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *  Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *  rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *  If this test needs to manipulate schema or truncate tables, then either:
 *     a. Do all that using setupHeadless() and Civi\Test.
 *     b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Banking_Analyser_RulesAnalyserTest extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
   * Test plan is:
   *   - configure a rules analyser
   *   - create a simple rule
   *   - create a transaction
   *   - see if the rule matches
   */
  public function testRulesAnalyserBasic():void {
    // get status IDs
    $payment_states  = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
    $state_processed = (int) $payment_states['processed']['id'];

    // create a transaction to process
    $test_contact = $this->traitCallAPISuccess('Contact', 'getsingle', ['id' => $this->createContact()]);
    $transaction_source = $this->getRandomString();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $transaction_id = $this->createTransaction(
      [
        'purpose' => 'CiviBanking Test',
        'source' => $transaction_source,
        'financial_type_id' => $financial_type_id,
        'amount' => 11.11,
        'payment_instrument_id' => $payment_instrument_id,
        'name' => $test_contact['display_name'],
      ]
    );

    // configure a matcher. this should NOT automatically fire yet:
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ContributionMatcher-01.civibanking'));

    // run the matcher and verify that it was NOT executed
    $this->runMatchers();
    $current_transaction = $this->getTransaction($transaction_id);
    $this->assertNotEquals($state_processed, $current_transaction['status_id'], "The transaction should not have been processed.");
    CRM_Banking_Matcher_Engine::clearCachedInstance();


    // now add the rules analyser
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('analyser/configuration/RulesAnalyser-01.civibanking'));

    // and add a rule to help us out here
    $rule_id = CRM_Banking_Rules_Rule::addRule(
      [
        'name'         => $test_contact['display_name'],
        'amount_min'   => 11.10,
        'amount_max'   => 11.12,
        //'party_ba_ref' => 'todo',
        //'tx_reference' => 'todo',
        //'tx_purpose'   => 'CiviBanking Test',
      ],
      [
        ['set_param_name' => 'contact_id', 'set_param_value' => $test_contact['id']],
        ['set_param_name' => 'financial_type_id', 'set_param_value' => 1],
      ]
    );
    $this->assertNotEmpty($rule_id, "Rule could not be created.");

    // run the matcher again and verify that this time IT WAS executed
    $this->runMatchers();
    $latest_contribution = $this->getLatestContribution();
    $this->assertNotNull($latest_contribution, "No contribution was created, the matcher failed.");

    // create a transaction to process
    $transaction_source = $this->getRandomString();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $this->createTransaction(
      [
        'purpose' => 'This is a donation',
        'source' => $transaction_source,
        'financial_type_id' => $financial_type_id,
        'payment_instrument_id' => $payment_instrument_id,
        'contact_id' => $this->createContact(),
        'name' => "doesn't matter"
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ContributionMatcher-01.civibanking'));


    // check the result
    $created_contribution = $this->getLatestContribution();
//    if ($previous_contribution) {
//      $this->assertNotEquals($created_contribution['id'], $previous_contribution['id'], "No contribution created!");
//    }
    $this->assertTrue(key_exists('contribution_source', $created_contribution),
                      "Source was not passed to the created contribution");
    $this->assertEquals($created_contribution['contribution_source'], $transaction_source,
                        "Source was not passed to the created contribution");
    $this->assertEquals($created_contribution['financial_type_id'], $financial_type_id,
                        "Financial Type was not passed to the created contribution");
    $this->assertEquals($created_contribution['payment_instrument_id'], $payment_instrument_id,
                        "PaymentInstrument was not passed to the created contribution");
  }

}
