<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking - PHPUnit tests               |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
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

declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

/**
 * Tests for the RegexAnalyser class.
 *
 * @covers CRM_Banking_PluginImpl_Matcher_RegexAnalyser
 *
 * @group headless
 */
class CRM_Banking_MatchersIntegrationLegacyTest extends CRM_Banking_TestBase {

  /**
   * Test regex and create contribution matcher in combination.
   */
  public function testRegexAndCreateContributionMatcher() {
    $contactId = $this->createContact();
    $transactionId = $this->createTransaction(
        [
          'purpose' => 'This is a donation',
          'financial_type' => 'CreditCard',
          'contact_id' => $contactId,
    // NOTE: Must be set, otherwise there occurs an error while matching CreateContribution.
          'name' => '',
        ]
    );

    $this->createRegexAnalyser(
        [
            [
              'fields' => ['financial_type'],
              'pattern' => '/(?P<pi>CreditCard|DebitCard)/',
              'actions' => [
                    [
                      'from' => 'pi',
                      'to' => 'payment_instrument_id',
                      'action' => 'map',
                      'mapping' => [
                        'CreditCard' => 1,
                        'DebitCard' => 2,
                      ],
                    ],
              ],
            ],
            [
              'fields' => ['purpose'],
              'pattern' => '/donation/i',
              'actions' => [
                    [
                      'action' => 'set',
                      'value' => 1,
                      'to' => 'financial_type_id',
                    ],
              ],
            ],
        ]
    );

    $createContributionMatcherId = $this->createCreateContributionMatcher();
    $transactionBeforeRun = $this->getTransaction($transactionId);
    $this->runMatchers();

    $transactionAfterRun = $this->getTransaction($transactionId);
    $parsedDataBefore = json_decode($transactionBeforeRun['data_parsed']);
    $parsedDataAfter = json_decode($transactionAfterRun['data_parsed']);

    static::assertSame(
      'CreditCard',
      $parsedDataBefore->financial_type,
      "The transaction's financial type ID is not correct."
    );
    static::assertSame(
      1,
      $parsedDataAfter->payment_instrument_id,
      "The transaction's payment instrument ID is not correct."
    );

    $contribution = $this->getLatestContribution();
    $this->assertEquals(
        $contactId,
        $contribution['contact_id'],
        E::ts("The contribution's contact ID is not correct.")
    );
    $this->assertEquals(
        1,
        $contribution['payment_instrument_id'],
        E::ts("The contributions' payment instrument ID is not correct.")
    );
    $this->assertEquals(
        1,
        $contribution['financial_type_id'],
        E::ts("The contributions' financial type ID is not correct.")
    );
  }

}
