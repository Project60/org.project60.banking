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

use Civi\API\Exception\NotImplementedException;
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
     * Test the most simple regex matcher.
     */
    public function testSimpleRegexMatcher()
    {
        $this->markTestSkipped(E::ts('This test is not fully implemented.'));

        $transactionId = $this->createTransaction();

        $transactionBeforeRun = $this->getTransaction($transactionId);

        // TODO: Configure regex matcher.
        $matcherId = $this->createRegexAnalyser();

        $this->runMatchers();

        $transactionAfterRun = $this->getTransaction($transactionId);

        // TODO: Assert what should be.
    }
}
