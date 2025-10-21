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

/**
 * @covers CRM_Banking_PluginImpl_Matcher_RulesAnalyser
 *
 * @group headless
 */
class CRM_Banking_Analyser_RulesAnalyserTest extends CRM_Banking_TestBase {

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
    $latest_contribution_before = $this->getLatestContribution();
    $this->runMatchers();
    $current_transaction = $this->getTransaction($transaction_id);
    $this->assertNotEquals($state_processed, $current_transaction['status_id'], "The transaction should not have been marked 'processed'.");
    $latest_contribution = $this->getLatestContribution();
    if ($latest_contribution_before) {
      $this->assertEquals($latest_contribution_before['id'], $latest_contribution['id'], "A contribution should not have been created");
    } else {
      $this->assertNull($latest_contribution, "A contribution should not have been created");
    }
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
    //  if this is the case, it's because the rule was executed and supplied the required attributes
    $this->runMatchers();
    $post_matcher_contribution = $this->getLatestContribution();
    $this->assertNotNull($post_matcher_contribution, "No contribution was created, the matcher failed.");
  }
}
