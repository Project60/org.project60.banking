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

use CRM_Banking_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

include_once "CRM/Banking/Helpers/OptionValue.php";

/**
 * Base class to support the old test cases
 *
 * @group headless
 */
class CRM_Banking_LegacyTestBase extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface
{
  use \Civi\Test\Api3TestTrait {
    callAPISuccess as protected traitCallAPISuccess;
  }

  public function testLegacyFunctions()
  {
    $matcher = $this->createCreateContributionMatcher();
    $this->assertNotEmpty($matcher, "Helper function \"Create Contribution matcher\" doesn't work.");

    $analyser = $this->createRegexAnalyser();
    $this->assertNotEmpty($analyser, "Helper function \"Create Create RegexAnalyser\" doesn't work.");
  }

  /**
   * Create a "create contribution" matcher with simple defaults.
   *
   * @param array $configuration
   *  The configuration for the matcher. Only set values will overwrite defaults.
   *
   * @return int
   *   The matcher ID.
   *
   * @author B. Zschiedrich (zschiedrich@systopia.de)
   */
  protected function createCreateContributionMatcher(array $configuration = []): int
  {
    $defaultConfiguration = [
      'required_values' => [
        'btx.financial_type_id',
        'btx.payment_instrument_id',
        'btx.contact_id',
      ],
      'value_propagation' => [
        'btx.financial_type_id' => 'contribution.financial_type_id',
        'btx.payment_instrument_id' => 'contribution.payment_instrument_id',
      ],
      'lookup_contact_by_name' => [
        'mode' => 'off'
      ]
    ];
    $mergedConfiguration = array_merge($defaultConfiguration, $configuration);
    $matcherId = $this->createMatcher('match', 'matcher_create', $mergedConfiguration);
    return $matcherId;
  }

  /**
   * Create a regex analyser with simple defaults.
   *
   * @param array|null $rules The rules to apply for the matcher. If null, default rules are used,
   *                          otherwise the given ones.
   * @param array $configuration The configuration for the matcher. Only set values will overwrite defaults.
   *
   * @return int The matcher ID.
   */
  public function createRegexAnalyser(array $rules = null, array $configuration = []): int
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

    $finalRules = $rules === null ? $defaultRules : $rules;
    $defaultConfiguration = ['rules' => $finalRules];
    $mergedConfiguration = array_merge($defaultConfiguration, $configuration);
    return $this->createMatcher('match', 'analyser_regex', $mergedConfiguration);
  }
}
