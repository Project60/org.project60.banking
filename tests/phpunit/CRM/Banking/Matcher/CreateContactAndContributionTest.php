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
class CRM_Banking_Matcher_CreateContactAndContributionTest extends CRM_Banking_TestBase {

  /**
   * Basic test to see if the contribution matcher fires
   *   and passes on the respective variables
   */
  public function testContributionMatcherFires():void {
    // get the previous existing contribution and contact
    $previous_contribution = $this->getLatestContribution();
    $previous_contact = $this->getLatestContact();

    // create a transaction to process
    $transaction_source = $this->getRandomString();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $first_name = 'Dan';
    $last_name = 'Donator';
    $this->createTransaction(
      [
        'purpose' => 'This is a donation',
        'source_contribution' => $transaction_source,
        'source_contact' => $transaction_source,
        'financial_type_id' => $financial_type_id,
        'payment_instrument' => $payment_instrument_id,
        'name' => $first_name . ' ' . $last_name,
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/CreateContactAndContributionMatcher-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check the result
    $created_contribution = $this->getLatestContribution();
    if (is_array($previous_contribution)) {
      self::assertNotEquals($created_contribution['id'], $previous_contribution['id'], 'No contribution created!');
    }
    static::assertArrayHasKey(
      'contribution_source',
      $created_contribution,
      'Source was not passed to the created contribution'
    );
    self::assertEquals($created_contribution['contribution_source'], $transaction_source,
                        'Source was not passed to the created contribution');
    self::assertEquals($created_contribution['financial_type_id'], $financial_type_id,
                        'Financial Type was not passed to the created contribution');
    self::assertEquals($created_contribution['payment_instrument_id'], $payment_instrument_id,
                        'PaymentInstrument was not passed to the created contribution');

    $created_contact = $this->getLatestContact();
    if (count($previous_contact) > 0) {
      self::assertNotEquals($created_contact['id'], $previous_contact['id'], 'No contact created!');
    }
    static::assertArrayHasKey(
      'source',
      $created_contact,
      'Source was not passed to the created contact'
    );
    self::assertEquals($created_contact['source'], $transaction_source,
                        'Source was not passed to the created contact');
    self::assertEquals($created_contact['first_name'], $first_name,
                        'First name was not passed to the created contact');
    self::assertEquals($created_contact['last_name'], $last_name,
                        'Last name was not passed to the created contact');
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
      $this->getTestResourcePath('matcher/configuration/CreateContactAndContributionMatcher-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check the result
    $last_contribution = $this->getLatestContribution();
    if (is_array($previous_contribution)) {
      if (is_array($last_contribution)) {
        self::assertNotEquals($last_contribution['id'], $previous_contribution['id'],
                               'A new contribution was created, even though a required value was missing.');
      }
    }
    else {
      self::assertEmpty($last_contribution,
                        'A new contribution was created, even though a required value was missing.');
    }
  }

}
