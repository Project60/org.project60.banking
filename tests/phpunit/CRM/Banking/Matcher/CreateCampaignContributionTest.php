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

use CRM_Banking_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

/**
 * Tests for the new CampaignContributionMatcher
 *
 * @see https://github.com/Project60/org.project60.banking/issues/296
 *
 * @group headless
 */
class CRM_Banking_Matcher_CreateCampaignContributionMatcherTest extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return parent::setUpHeadless();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
    parent::tearDown();
  }

  /**
   * Basic test to see if the contribution matcher fires
   *   and passes on the respective variables
   */
  public function testCampaignMatcherBasic():void {
    // step 1: create a simple scenario
    $contact_id = $this->createContact();
    $campaign_id = $this->createCampaign();
    $this->createActivity([
      'target_id'          => $contact_id,
      'activity_status_id' => 'Completed',
    ]);

    // step 2: configure a simple campaign matcher
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/CampaignMatcher-01.civibanking'));

    // step 3: create a transaction
    $transaction1_id = $this->createTransaction([
      'purpose' => "This transaction should trigger a campaign-based contribution",
      'campaign_id' => $campaign_id,
      'contact_id' => $contact_id
    ]);

    // get the previous existing contribution
    $previous_contribution = $this->getLatestContribution();
    $this->assertNull($previous_contribution, "there should NOT be a contribution at this point. Or is this too restrictive?");

    // run the matcher
    $this->runMatchers();

    // check the result
    $created_contribution = $this->getLatestContribution();
    $this->assertNotNull($created_contribution, "No contribution generated. Something doesn't work.");
  }

  /**
   * Basic test to see respects
   */
  public function testCampaignMatcherBasicNegative():void {
    // step 1: create a simple scenario
    $contact_id = $this->createContact();
    $campaign_id = $this->createCampaign();
    $this->createActivity([
        'target_id'          => $contact_id,
        'activity_status_id' => 'Completed',
        // default is 40 days, so this should fail:
        'activity_date_time' => date('Y-m-d', strtotime("now -41 days"))
      ]);

    // step 2: configure a simple campaign matcher
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/CampaignMatcher-01.civibanking'));

    // step 3: create a transaction
    $transaction1_id = $this->createTransaction([
      'purpose' => "This transaction should trigger a campaign-based contribution",
      'campaign_id' => $campaign_id,
      'contact_id' => $contact_id
    ]);

    // get the previous existing contribution
    $last_contribution = $this->getLatestContribution();
    $this->assertNull($last_contribution, "there should NOT be a contribution at this point. Or is this too restrictive?");

    // run the matcher
    $this->runMatchers();

    // check the result
    $new_last_contribution = $this->getLatestContribution();
    $this->assertEquals($last_contribution, $new_last_contribution, "No contribution should've been generated. Something doesn't work right.");
  }
}
