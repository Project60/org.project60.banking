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
 * Tests for the CreateContribution class.
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
class CRM_Banking_CreateContributionLegacyTest extends CRM_Banking_LegacyTestBase
{
    public function testSimpleCreateContributionMatcher()
    {
        $contactId = $this->createContact();
        $this->createTransaction(
            [
                'contact_id' => $contactId,
                'name' => '', // NOTE: Must be set, otherwise there occurs an error while matching CreateContribution.
                'payment_instrument_id' => 1,
                'financial_type_id' => 1,
            ]
        );

        $this->createCreateContributionMatcher();
        $this->runMatchers();

        // check results
        $contribution = $this->getLatestContribution();
        $this->assertEquals(
            $contactId,
            $contribution['contact_id'],
            E::ts("The contact ID is not correct.")
        );
        $this->assertEquals(
            1,
            $contribution['payment_instrument_id'],
            E::ts("The payment instrument ID is not correct.")
        );
        $this->assertEquals(
            1,
            $contribution['financial_type_id'],
            E::ts("The financial type ID is not correct.")
        );
    }
}
