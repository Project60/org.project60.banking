<?php

/*-------------------------------------------------------+
| Project 60 - CiviBanking - PHPUnit tests               |
| Copyright (C) 2020 SYSTOPIA                            |
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

use CRM_Banking_ExtensionUtil as E;

/**
 * Tests for the RegexAnalyser class.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *  Simply create corresponding functions (e.g. 'hook_civicrm_post(...)' or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *  rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *  If this test needs to manipulate schema or truncate tables, then either:
 *     a. Do all that using setupHeadless() and Civi\Test.
 *     b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Banking_MatchersIntegrationTest extends CRM_Banking_TestBase
{
    /**
     * Test regex and create contribution matcher in combination.
     */
    public function testRegexAndCreateContributionMatcher()
    {
        $this->markTestSkipped(E::ts('This test is not fully implemented.'));

        $contactId = $this->createContact();

        $transactionId = $this->createTransaction(
            [
                'purpose' => 'This is a donation',
                'financial_type' => 'CreditCard',
            ]
        );

        $matcherId = $this->createRegexAnalyser(
            [
                [
                    'fields' => ['financial_type'],
                    'pattern' => '/(?P<pi>CreditCard|DebitCard)/',
                    'actions' => [
                        [
                            'from' => 'pi',
                            'to' => 'payment_instrument_id',
                            'actions' => 'map',
                            'mapping' => [
                                'CreditCard' => 1,
                                'DebitCard' => 2,
                            ]
                        ]
                    ]
                ],
                [
                    'fields' => ['purpose'],
                    'pattern' => '/Donation/i',
                    'actions' => [
                        [
                            'action' => 'set',
                            'value' => 1,
                            'to' => 'financial_type_id',
                        ],
                        [
                            'action' => 'set',
                            'value' => $contactId,
                            'to' => 'contact_id',
                        ]
                    ]
                ]
            ]
        );

        $matcherId = $this->createCreateContributionMatcher([]);

        $transactionBeforeRun = $this->getTransaction($transactionId);

        $this->runMatchers();

        $transactionAfterRun = $this->getTransaction($transactionId);

        $contribution = $this->getLatestContribution();

        // TODO: Assert that a contribution was created.
        // TODO: Assert if the changes to the contribution have been applied.
    }
}
