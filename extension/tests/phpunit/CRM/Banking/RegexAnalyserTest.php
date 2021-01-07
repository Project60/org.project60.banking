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
 *  Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *  rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *  If this test needs to manipulate schema or truncate tables, then either:
 *     a. Do all that using setupHeadless() and Civi\Test.
 *     b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Banking_RegexAnalyserTest extends CRM_Banking_TestBase
{
    /**
     * Test a set action.
     */
    public function testSetAction()
    {
        $transactionId = $this->createTransaction(
            [
                'purpose' => 'This is a donation',
            ]
        );

        $matcherId = $this->createRegexAnalyser(
            [
                [
                    'fields' => ['purpose'],
                    'pattern' => '/donation/i',
                    'actions' => [
                        [
                            'action' => 'set',
                            'value' => 1,
                            'to' => 'financial_type_id',
                        ]
                    ]
                ]
            ]
        );

        $this->runMatchers();

        $transactionAfterRun = $this->getTransaction($transactionId);

        $parsedDataAfter = json_decode($transactionAfterRun['data_parsed']);

        $this->assertAttributeEquals(
            '1',
            'financial_type_id',
            $parsedDataAfter,
            E::ts("The financial type ID is not correctly set.")
        );
    }

    /**
     * Test that a test action does not set if not matched.
     */
    public function testSetActionDoesNotMatch()
    {
        $transactionId = $this->createTransaction(
            [
                'purpose' => 'This is a nothing',
            ]
        );

        $matcherId = $this->createRegexAnalyser(
            [
                [
                    'fields' => ['purpose'],
                    'pattern' => '/donation/i',
                    'actions' => [
                        [
                            'action' => 'set',
                            'value' => 1,
                            'to' => 'financial_type_id',
                        ]
                    ]
                ]
            ]
        );

        $this->runMatchers();

        $transactionAfterRun = $this->getTransaction($transactionId);

        $parsedDataAfter = json_decode($transactionAfterRun['data_parsed']);

        $this->assertObjectNotHasAttribute(
            'financial_type_id',
            $parsedDataAfter,
            E::ts("The financial type ID is set but should not.")
        );
    }

    /**
     * Test a map action.
     */
    public function testMapAction()
    {
        $transactionId = $this->createTransaction(
            [
                'financial_type' => 'CreditCard'
            ]
        );

        $transactionBeforeRun = $this->getTransaction($transactionId);

        $matcherId = $this->createRegexAnalyser(
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
                            ]
                        ]
                    ]
                ]
            ]
        );

        $this->runMatchers();

        $transactionAfterRun = $this->getTransaction($transactionId);

        $parsedDataAfter = json_decode($transactionAfterRun['data_parsed']);

        $this->assertAttributeEquals(
            '1',
            'payment_instrument_id',
            $parsedDataAfter,
            E::ts('The payment instrument ID is not correctly set.')
        );
    }
}
