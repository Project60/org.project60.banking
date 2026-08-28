<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking - Unit Test                   |
| Copyright (C) 2023 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
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

declare(strict_types = 1);

/**
 * Test ContributionMatcher module
 *
 * @covers CRM_Banking_PluginImpl_Matcher_ExistingContribution
 *
 * @group headless
 */
class CRM_Banking_Matcher_MatchContributionMatcherTest extends CRM_Banking_TestBase {

  /**
   * Basic test to see if the contribution matcher fires
   *   and passes on the respective variables
   */
  public function testContributionMatcherFires():void {
    $pending_contribution = $this->createContribution([
      'contribution_status_id' => 'Pending',
    ]);

    // create a transaction to process
    $this->createTransaction(
      [
        'purpose' => 'This is a donation',
        'name' => "doesn't matter",
        'amount' => $pending_contribution['total_amount'],
        'contact_id'   => $pending_contribution['contact_id'],
        'booking_date' => date('Y-m-d', strtotime($pending_contribution['receive_date'])),
        'value_date'   => date('Y-m-d', strtotime($pending_contribution['receive_date'])),
        'currency'     => $pending_contribution['currency'],
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ExistingContribution-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check if the contribution status has been update
    $completed_contribution = $this->callAPISuccess(
      'Contribution', 'getsingle', ['id' => $pending_contribution['id']]);

    $this->assertEquals(1, $completed_contribution['contribution_status_id'], "Contribution wasn't completed.");
  }

  /**
   * With several candidates above the auto-exec threshold, the most probable
   * one must be executed — not the one the database happened to return first.
   */
  public function testAutoExecutionPicksMostProbableSuggestion(): void {
    $contact_id = $this->createContact();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $shared = [
      'contact_id'             => $contact_id,
      'contribution_status_id' => 'Pending',
      'total_amount'           => 42.0,
      'financial_type_id'      => $financial_type_id,
      'payment_instrument_id'  => $payment_instrument_id,
    ];
    // The older contribution is created first, i.e. gets the lower id and is
    // returned first by the candidate query; the date penalty leaves it at ~0.9.
    $older_contribution = $this->createContribution($shared + ['receive_date' => date('Y-m-d', strtotime('-30 days'))]);
    $recent_contribution = $this->createContribution($shared + ['receive_date' => date('Y-m-d')]);

    $this->createTransaction([
      'purpose'      => 'Late-then-on-time donor',
      'name'         => "doesn't matter",
      'amount'       => 42.0,
      'contact_id'   => $contact_id,
      'booking_date' => date('Y-m-d'),
      'value_date'   => date('Y-m-d'),
      'currency'     => $recent_contribution['currency'],
    ]);
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ExistingContribution-02.civibanking'));

    $this->runMatchers();

    // get() restores the stored suggestions, find()/fetch() would not.
    $transaction = new CRM_Banking_BAO_BankTransaction();
    $transaction->get('id', (string) $this->getLatestTransactionId());
    static::assertEquals($this->getTxStatusID('processed'), $transaction->status_id, 'Transaction was not processed automatically.');
    $probabilities = [];
    foreach ($transaction->getSuggestionList() as $suggestion) {
      $probabilities[$suggestion->getParameter('contribution_id')] = $suggestion->getProbability();
    }
    static::assertCount(2, $probabilities, 'Both pending contributions should have been suggested.');
    static::assertGreaterThan($probabilities[$older_contribution['id']], $probabilities[$recent_contribution['id']]);

    $recent = $this->callAPISuccess('Contribution', 'getsingle', ['id' => $recent_contribution['id']]);
    $older = $this->callAPISuccess('Contribution', 'getsingle', ['id' => $older_contribution['id']]);
    static::assertEquals(1, $recent['contribution_status_id'], 'The most probable contribution was not completed.');
    static::assertEquals(2, $older['contribution_status_id'], 'The less probable contribution was reconciled instead.');
  }

}
