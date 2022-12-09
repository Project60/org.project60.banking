<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking - PHPUnit tests               |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
|         B. Endres (endres@systopia.de)                 |
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
 *
 * Mainly tests the regex extraction
 *  and the actions: copy, copy_append, copy_ltrim_zeros, set(ok), align_date, unset, strtolower, sha1, preg_replace, calculate, map(ok)
 */
class CRM_Banking_RegexAnalyserTest  extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface
{
    /**
     * Test the 'set' action.
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
     * Test that the 'test' action does not set if not matched.
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
     * Test the 'map' action.
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

    /**
     * Test the 'copy' action.
     */
    public function testCopyAction()
    {
        // setup
        $transaction1_id = $this->createTransaction(['purpose' => "here's your code X92873X2323X, alright!?"]);
        $transaction2_id = $this->createTransaction(['purpose' => "here's an invalid code X92873Y2323X, alright!?"]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['purpose'],
                    'pattern' => '/X(?P<part1>[0-9]+)X(?P<part2>[0-9]+)X/i',
                    'actions' => [
                        [
                            'action' => 'copy',
                            'from' => 'part1',
                            'to' => 'part1_extracted',
                        ],
                        [
                            'action' => 'copy',
                            'from' => 'part2',
                            'to' => 'part2_extracted',
                        ],
                    ],
                ],
            ]
        );

        // run matcher
        $this->runMatchers([$transaction1_id, $transaction2_id]);

        // check transaction 1
        $data_parsed = $this->getTransactionDataParsed($transaction1_id);
        $this->assertArrayHasKey('part1_extracted', $data_parsed, 'Parsed data not copied');
        $this->assertEquals('92873', $data_parsed['part1_extracted'], 'Parsed data is wrong');
        $this->assertArrayHasKey('part2_extracted', $data_parsed, 'Parsed data not copied');
        $this->assertEquals('2323', $data_parsed['part2_extracted'], 'Parsed data is wrong');

        // check transaction 2
        $data_parsed = $this->getTransactionDataParsed($transaction2_id);
        $this->assertArrayNotHasKey('part1_extracted', $data_parsed, 'This should not have been matched and extracted');
        $this->assertArrayNotHasKey('part2_extracted', $data_parsed, 'This should not have been matched and extracted');
    }

    /**
     * Test the 'copy_append' action.
     */
    public function testCopyAppendAction()
    {
        // setup
        $transaction_id = $this->createTransaction([
            'purpose'      => "here's your code X92873X2323X, alright!?",
            'target_field' => 'stuff'
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['purpose'],
                    'pattern' => '/X(?P<part1>[0-9]+)X(?P<part2>[0-9]+)X/i',
                    'actions' => [
                        [
                            'action' => 'copy_append',
                            'from' => 'part1',
                            'to' => 'target_field',
                        ],
                        [
                            'action' => 'copy_append',
                            'from' => 'part2',
                            'to' => 'target_field',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('target_field', $data_parsed, 'Parsed data not copied');
        $this->assertEquals('stuff928732323', $data_parsed['target_field'], 'Parsed data is wrong');
    }

    /**
     * Test the 'copy_ltrim_zeros' action.
     */
    public function testSetUnsetAction()
    {
        // setup
        $transaction_id = $this->createTransaction([
           'field_1' => "YO",
       ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['field_1'],
                    'pattern' => '/YO/i',
                    'actions' => [
                        [
                            'action' => 'set',
                            'to' => 'field2',
                            'value' => 'YO',
                        ],
                        [
                            'action' => 'unset',
                            'to' => 'field_1',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('field2', $data_parsed, "Set rule didn't fire");
        $this->assertEquals('YO', $data_parsed['field2'], "Set rule didn't fire");
        $this->assertArrayNotHasKey('field1', $data_parsed, "Unset rule didn't fire");
    }

    /**
     * Test the 'copy_ltrim_zeros' action.
     */
    public function testStrtolowerAction()
    {
        // setup
        $transaction_id = $this->createTransaction([
           'field_1' => "YO",
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['field_1'],
                    'pattern' => '/(?P<match>YO)/',
                    'actions' => [
                        [
                            'action' => 'strtolower',
                            'from' =>  'match',
                            'to' => 'field2',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('field2', $data_parsed, "Set rule didn't fire");
        $this->assertEquals('yo', $data_parsed['field2'], "strtolower didn't work");
    }

    /**
     * Test the 'sha1' action.
     */
    public function testSHA1Action()
    {
        // setup
        $transaction_id = $this->createTransaction([
           'data' => "Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit...",
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['data'],
                    'pattern' => '/^(?P<match>.*)$/',
                    'actions' => [
                        [
                            'action' => 'sha1',
                            'from' =>  'match',
                            'to' => 'sha1',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('sha1', $data_parsed, "Set rule didn't fire");
        $this->assertEquals('54e965b871ef62db46596a9d127d00d58dee6d3a', $data_parsed['sha1'], "sha1 didn't work");
    }

    /**
     * Test the 'sprint' action.
     */
    public function testSprintfAction()
    {
        // setup
        $transaction_id = $this->createTransaction([
           'data' => "1.234",
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['data'],
                    'pattern' => '/^(?P<match>.*)$/',
                    'actions' => [
                        [
                            // note that the %07 prefix marks the total number of characters in the string,
                            //  not just leading zeroes, see https://stackoverflow.com/questions/28739818
                            'action' => 'sprint:HA-%07.2f',
                            'from' =>  'match',
                            'to' => 'formatted',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('formatted', $data_parsed, "Set rule didn't fire");
        $this->assertEquals('HA-0001.23', $data_parsed['formatted'], "sprint didn't work");
    }


    /**
     * Test the 'lookup' action.
     */
    public function testLookupAction()
    {
        // setup
        $contact_id = $this->createContact(['external_identifier' => 'testLookupAction']);
        $contact = civicrm_api3('Contact', 'get', ['id' => $contact_id]);
        $transaction_id = $this->createTransaction([
            'ex_id' => "testLookupAction",
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['ex_id'],
                    'pattern' => '/^(?P<match>.*)$/',
                    'actions' => [
                        [
                            'action' => 'lookup:Contact,id,external_identifier',
                            'from' =>  'match',
                            'to' => 'contact_id',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('contact_id', $data_parsed, "Set rule didn't fire");
        $this->assertEquals($contact_id, $data_parsed['contact_id'], "lookup didn't work");
    }

    /**
     * Test the 'api' action.
     */
    public function testApiAction()
    {
        // setup
        $contact_id = $this->createContact(['external_identifier' => 'ApiAction', 'first_name' => 'Jenny']);
        $transaction_id = $this->createTransaction([
           'ex_id' => "ApiAction",
           'first_name' => 'Jenny'
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['ex_id'],
                    'pattern' => '/^(?P<match>.*)$/',
                    'actions' => [
                        [
                            'action' => 'api:Contact:get:contact_id',
                            'const_contact_type' => 'Individual',
                            'param_first_name' => 'first_name',
                            'param_external_identifier' => 'match',
                            'to' => 'contact_id',
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('contact_id', $data_parsed, "Set rule didn't fire");
        $this->assertEquals($contact_id, $data_parsed['contact_id'], "lookup didn't work");
    }

    /**
     * Test the 'api' action via sql
     */
    public function testApiActionSql()
    {
        // setup
        $contact_id = $this->createContact(['external_identifier' => 'ApiAction', 'first_name' => 'Jenny']);
        $transaction_id = $this->createTransaction([
           'ex_id' => "ApiAction",
           'first_name' => 'Jenny'
        ]);
        $this->createRegexAnalyser(
            [
                [
                    'fields' => ['ex_id'],
                    'pattern' => '/^(?P<match>.*)$/',
                    'actions' => [
                        [
                            'action' => 'api:Contact:get:id',
                            'const_contact_type' => 'Individual',
                            'param_first_name' => 'first_name',
                            'param_external_identifier' => 'match',
                            'to' => 'contact_id',
                            'sql' => true
                        ],
                    ],
                ],
            ]
        );

        // check result
        $this->runMatchers([$transaction_id]);
        $data_parsed = $this->getTransactionDataParsed($transaction_id);
        $this->assertArrayHasKey('contact_id', $data_parsed, "Set rule didn't fire");
        $this->assertEquals($contact_id, $data_parsed['contact_id'], "lookup didn't work");
    }

}
