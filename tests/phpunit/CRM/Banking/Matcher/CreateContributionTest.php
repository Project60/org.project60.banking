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
 * Test CreateContributionMatcher module
 *
 * @covers CRM_Banking_PluginImpl_Matcher_CreateContribution
 *
 * @group headless
 */
class CRM_Banking_Matcher_CreateContributionMatcherTest extends CRM_Banking_TestBase {

  /**
   * Basic test to see if the contribution matcher fires
   *   and passes on the respective variables
   */
  public function testContributionMatcherFires():void {
    // get the previous existing contribution
    $previous_contribution = $this->getLatestContribution();

    // create a transaction to process
    $transaction_source = $this->getRandomString();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = (int) $this->getRandomOptionValue('payment_instrument');
    $this->createTransaction(
      [
        'purpose' => 'This is a donation',
        'source' => $transaction_source,
        'financial_type_id' => $financial_type_id,
        'payment_instrument_id' => $payment_instrument_id,
        'contact_id' => $this->createContact(),
        'name' => "doesn't matter",
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ContributionMatcher-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check the result
    $created_contribution = $this->getLatestContribution();
    static::assertNotNull($created_contribution);
    if (NULL !== $previous_contribution) {
      static::assertNotEquals($created_contribution['id'], $previous_contribution['id'], 'No contribution created!');
    }
    static::assertSame(
      $created_contribution['source'],
      $transaction_source,
      'Source was not passed to the created contribution'
    );
    static::assertSame(
      $created_contribution['financial_type_id'],
      $financial_type_id,
      'Financial Type was not passed to the created contribution'
    );
    static::assertSame(
      $created_contribution['payment_instrument_id'],
      $payment_instrument_id,
      'PaymentInstrument was not passed to the created contribution'
    );
  }

  /**
   * Basic test to see if the contribution matcher does not fire
   *   and passes on the respective variables
   */
  public function testContributionMatcherDoesntFire():void {
    // get the previous existing contribution
    $previous_contribution = $this->getLatestContribution();

    // create a transaction to process
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $this->createTransaction(
      [
        // We don't set the 'source', but it's required!
        'purpose' => 'This is a donation',
        'financial_type_id' => $financial_type_id,
        'payment_instrument_id' => $payment_instrument_id,
        'contact_id' => $this->createContact(),
        'name' => "doesn't matter",
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ContributionMatcher-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check the result
    $last_contribution = $this->getLatestContribution();
    if (NULL !== $previous_contribution) {
      if (NULL !== $last_contribution) {
        static::assertNotEquals($last_contribution['id'], $previous_contribution['id'],
                               'A new contribution was created, even though a required value was missing.');
      }
    }
    else {
      static::assertNull(
        $last_contribution,
        'A new contribution was created, even though a required value was missing.'
      );
    }
  }

}
