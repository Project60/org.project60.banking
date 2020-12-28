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
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
    }

    const PRIMARY_TRANSACTION_FIELDS = [
        'version', 'debug', 'amount', 'bank_reference', 'value_date', 'booking_date', 'currency', 'type_id',
        'status_id', 'data_raw', 'data_parsed', 'ba_id', 'party_ba_id', 'tx_batch_id', 'sequence'
    ];

    const MATCHER_WEIGHT_STEP = 10;

    protected $transactionReferenceCounter = 0;

    protected $matcherWeight = 10;

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
        $this->matcherWeight = 10;
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Remove 'xdebug' result key set by Civi\API\Subscriber\XDebugSubscriber
     *
     * This breaks some tests when xdebug is present, and we don't need it.
     *
     * @param $entity
     * @param $action
     * @param $params
     * @param null $checkAgainst
     *
     * @return array|int
     */
    protected function callAPISuccess(string $entity, string $action, array $params, $checkAgainst = null)
    {
        $result = $this->traitCallAPISuccess($entity, $action, $params, $checkAgainst);

        if (is_array($result)) {
            unset($result['xdebug']);
        }

        return $result;
    }

    /**
     * Create a contact and return its ID.
     * @return int The ID of the created contact.
     */
    protected function createContact(): int
    {
        $contact = $this->callAPISuccess(
            'Contact',
            'create',
            [
                'contact_type' => 'Individual',
                'email' => 'unittests@sepa.project60.org',
            ]
        );

        $contactId = $contact['id'];

        return $contactId;
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

        $result = $this->callAPISuccess('BankingTransaction', 'create', $transaction);

        return $result['id'];
    }

    protected function getTransaction(int $id): array
    {
        $result = $this->callAPISuccess(
            'BankingTransaction',
            'getsingle',
            [
                'id' => $id
            ]
        );

        unset($result['is_error']);

        return $result;
    }

    /**
     * Get the latest contribution.
     */
    protected function getLatestContribution()
    {
        $contribution = $this->callAPISuccessGetSingle(
            'Contribution',
            [
                'options' => [
                    'sort' => 'id DESC',
                    'limit' => 1,
                ],
            ]
        );

        return $contribution;
    }

    protected function createMatcher(
        string $type,
        string $class,
        array $configuration = [],
        array $parameters = []
    ): int {
        $typeId = $this->matcherClassNameToId($type);
        $classId = $this->matcherClassNameToId($class);

        $parameterDefaults = [
            'plugin_class_id' => $classId,
            'plugin_type_id' => $typeId,
            'name' => 'Test Matcher ' . $type,
            'description' => 'Test Matcher "' . $type . '" with class "' . $class . '"',
            'enabled' => 1,
            'weight' => $this->matcherWeight,
            'state' => '{}',
        ];

        $this->matcherWeight += self::MATCHER_WEIGHT_STEP;

        $mergedParameters = array_merge($parameterDefaults, $parameters);

        $matcher = $this->callAPISuccess('BankingPluginInstance', 'create', $mergedParameters);

        $configurationDefaults = [
            'auto_exec' => 1
        ];

        $mergedConfiguration = array_merge($configurationDefaults, $configuration);

        // Set the config via SQL (API causes issues):
        if (empty($matcher['id'])) {
            throw new Exception("Matcher could not be created.");
        } else {
            $configurationAsJson = json_encode($mergedConfiguration);

            CRM_Core_DAO::executeQuery(
                "UPDATE civicrm_bank_plugin_instance SET config=%1 WHERE id=%2;",
                [
                    1 => [$configurationAsJson, 'String'],
                    2 => [$matcher['id'], 'Integer']
                ]
            );
        }

        return $matcher['id'];
    }

    protected function createRegexAnalyser(array $rules = null, array $configuration = []): int
    {
        $defaultRules = [
            [
                'comment' => 'Austrian address type 1',
                'fields' => [
                    'address_line'
                ],
                'pattern' => '#^(?P<postal_code>[0-9]{4}) (?P<city>[\\w\/]+)[ ,]*(?P<street_address>.*)$#',
                'actions' => [
                    [
                        'from' => 'street_address',
                        'action' => 'copy',
                        'to' => 'street_address'
                    ],
                    [
                        'from' => 'postal_code',
                        'action' => 'copy',
                        'to' => 'postal_code'
                    ],
                    [
                        'from' => 'city',
                        'action' => 'copy',
                        'to' => 'city'
                    ]
                ]
            ],
            [
                'comment' => 'Austrian address type 2',
                'fields' => [
                    'address_line'
                ],
                'pattern' => '#^(?P<street_address>[^,]+).*(?P<postal_code>[0-9]{4}) +(?P<city>[\\w ]+)$#',
                'actions' => [
                    [
                        'from' => 'street_address',
                        'action' => 'copy',
                        'to' => 'street_address'
                    ],
                    [
                        'from' => 'postal_code',
                        'action' => 'copy',
                        'to' => 'postal_code'
                    ],
                    [
                        'from' => 'city',
                        'action' => 'copy',
                        'to' => 'city'
                    ]
                ]
            ]
        ];

        $finalRules = is_null($rules) ? $defaultRules : $rules;

        $defaultConfiguration = [
            'rules' => $finalRules
        ];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('match', 'analyser_regex', $mergedConfiguration);

        return $matcherId;
    }

    protected function createIgnoreMatcher(array $rules = [], array $configuration = []): int
    {
        $defaultConfiguration = [];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('TODO: Fill in type!', 'TODO: Fill in class!', $mergedConfiguration);

        return $matcherId;
    }

    protected function createSepaMatcher(array $configuration = []): int
    {
        $defaultConfiguration = [];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('TODO: Fill in type!', 'TODO: Fill in class!', $mergedConfiguration);

        return $matcherId;
    }

    protected function createDefaultOptionsMatcher(array $configuration = []): int
    {
        $defaultConfiguration = [];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('TODO: Fill in type!', 'TODO: Fill in class!', $mergedConfiguration);

        return $matcherId;
    }

    protected function createCreateContributionMatcher(array $configuration = []): int
    {
        $defaultConfiguration = [
            'required_values' => [
                'btx.financial_type_id',
                'btx.payment_instrument_id',
                'btx.campaign_id',
                'btx.identified_by'
            ],
            'value_propagation' => [
                'btx.financial_type_id' => 'contribution.financial_type_id',
                'btx.campaign_id' => 'contribution.campaign_id',
                'btx.payment_instrument_id' => 'contribution.payment_instrument_id'
            ],
            'lookup_contact_by_name' => [
                'mode' => 'off'
            ]
        ];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('match', 'matcher_create', $mergedConfiguration);

        return $matcherId;
    }

    protected function createExistingContributionMatcher(array $configuration = []): int
    {
        $defaultConfiguration = [];

        $mergedConfiguration = array_merge($defaultConfiguration, $configuration);

        $matcherId = $this->createMatcher('TODO: Fill in type!', 'TODO: Fill in class!', $mergedConfiguration);

        return $matcherId;
    }

    protected function matcherClassNameToId(string $className): int
    {
        $result = $this->callAPISuccess(
            'OptionValue',
            'getsingle',
            [
                'option_group_id' => 'civicrm_banking.plugin_classes',
                'name' => $className,
            ]
        );

        return $result['id'];
    }

    protected function matcherTypeNameToId(string $typeName): int
    {
        $result = $this->callAPISuccess(
            'OptionValue',
            'getsingle',
            [
                'option_group_id' => 'civicrm_banking.plugin_types',
                'name' => $typeName,
            ]
        );

        return $result['id'];
    }

    protected function runMatchers(): void
    {
        // TODO: Implement.
    }
}
