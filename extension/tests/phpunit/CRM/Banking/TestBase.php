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

use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use CRM_Banking_ExtensionUtil as E;

/**
 * The base class for all the unit tests.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Banking_TestBase extends \PHPUnit_Framework_TestCase implements
    HeadlessInterface,
    HookInterface,
    TransactionalInterface
{
    const PRIMARY_TRANSACTION_FIELDS = [
        'version', 'debug', 'amount', 'bank_reference', 'value_date', 'booking_date', 'currency', 'type_id',
        'status_id', 'data_raw', 'data_parsed', 'ba_id', 'party_ba_id', 'tx_batch_id', 'sequence'
    ];

    protected $transactionReferenceCounter = 0;

    public function setUpHeadless(): Civi\Test\CiviEnvBuilder
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
        return \Civi\Test::headless()
            ->installMe(__DIR__)
            ->apply();
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->transactionReferenceCounter = 0;
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    protected function createTransaction(array $parameters = []): int
    {
        $today = date('Y-m-d');

        $defaults = [
            'version' => 3,
            'bank_reference' => 'TestBankReference-' . $this->transactionReferenceCounter,
            'booking_date' => $today,
            'value_date' => $today,
            'currency' => 'EUR',
            'sequence' => $this->transactionReferenceCounter,
        ];

        $this->transactionReferenceCounter++;

        $transaction = array_merge($defaults, $parameters);

        // Fill parsed data:
        $parsedData = [];
        foreach ($transaction as $key => $value) {
            if (!in_array($key, self::PRIMARY_TRANSACTION_FIELDS)) {
                $parsedData[$key] = $value;
                unset($transaction[$key]);
            }
        }
        $transaction['data_parsed'] = json_encode($parsedData);

        $result = civicrm_api3('BankingTransaction', 'create', $transaction);

        return $result['id'];
    }
}
