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

/**
 * Test ContributionMatcher module
 *
 * @covers CRM_Banking_PluginImpl_Matcher_ExistingContribution
 *
 * @group headless
 */
class CRM_Banking_Matcher_MatchContributionMatcherTest extends CRM_Banking_TestBase {

  /**
   * Basic test to see if the contribution matcher fires
   *   and passes on the respective variables
   */
  public function testContributionMatcherFires():void {
    $pending_contribution = $this->createContribution([
      'contribution_status_id' => 'Pending',
    ]);

    // create a transaction to process
    $this->createTransaction(
      [
        'purpose' => 'This is a donation',
        'name' => "doesn't matter",
        'amount' => $pending_contribution['total_amount'],
        'contact_id'   => $pending_contribution['contact_id'],
        'booking_date' => date('Y-m-d', strtotime($pending_contribution['receive_date'])),
        'value_date'   => date('Y-m-d', strtotime($pending_contribution['receive_date'])),
        'currency'     => $pending_contribution['currency'],
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/ExistingContribution-01.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check if the contribution status has been update
    $completed_contribution = $this->callAPISuccess(
      'Contribution', 'getsingle', ['id' => $pending_contribution['id']]);

    $this->assertEquals(1, $completed_contribution['contribution_status_id'], "Contribution wasn't completed.");
  }
}
