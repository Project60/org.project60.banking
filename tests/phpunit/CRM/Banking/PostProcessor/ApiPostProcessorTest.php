<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking - Unit Test                   |
| Copyright (C) 2023 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
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
 * Test CreateContributionMatcher module
 *
 * @group headless
 */
class CRM_Banking_PostProcessor_ApiPostProcessorTest extends CRM_Banking_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
   * Basic test see if postprocessing works. It consists
   *  of a create contribution matcher and a postprocessor
   */
  public function testTagPostprocessor():void {
    $TAG_NAME = 'Tagged'; // as used in config file
    $this->getOrCreateTag($TAG_NAME, ['used_for' => 'civicrm_contact']);

    $contribution = $this->createContribution([
      'contribution_status_id' => 'Completed',
    ]);

    // create a transaction to process
    $contact_id = $contribution['contact_id'];
    $transaction_source = $this->getRandomString();
    $financial_type_id = $this->getRandomFinancialTypeID();
    $payment_instrument_id = $this->getRandomOptionValue('payment_instrument');
    $this->createTransaction(
      [
        'purpose'       => 'This is a cancellation',
        'name'          => "doesn't matter",
        'amount'        => -$contribution['total_amount'],
        'contact_id'    => $contact_id,
        'booking_date'  => date('Y-m-d', strtotime($contribution['receive_date'])),
        'value_date'    => date('Y-m-d', strtotime($contribution['receive_date'])),
        'currency'      => $contribution['currency'],
        'cancel_reason' => 'MD07'
      ]
    );

    // configure the matcher (copied from testContributionMatcherFires)
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/CancelExistingContribution-01.civibanking'));

    // configure a post-processor to tag the contact
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('post_processor/configuration/TagContact.civibanking'));

    // run the matcher
    $this->runMatchers();

    // now verify that the contact was tagged
    $this->assertEntityTagged($TAG_NAME, $contact_id, 'civicrm_contact',
                              "Contact was _NOT_ tagged, postprocessor not working");
  }

  /**
   * Basic test see if postprocessing works. It consists
   *  of an 'existing contribution' matcher and a 'contact deceased' post processor
   */
  public function testDeceasedPostprocessor():void {
    // plan:
    //  1) create completed contribution
    //  2) run matcher to mark cancelled set cancel_reason 'MD07' (deceased)
    //  3) run postprocessor to set contact to deceased if cancel_reason = MD07

    $contribution = $this->createContribution([
        'contribution_status_id' => 'Completed',
      ]);

    // create a transaction to process
    $this->createTransaction(
      [
        'purpose'       => 'This is a cancellation',
        'name'          => "doesn't matter",
        'amount'        => -$contribution['total_amount'],
        'contact_id'    => $contribution['contact_id'],
        'booking_date'  => date('Y-m-d', strtotime($contribution['receive_date'])),
        'value_date'    => date('Y-m-d', strtotime($contribution['receive_date'])),
        'currency'      => $contribution['currency'],
        'cancel_reason' => 'MD07'
      ]
    );

    $this->configureCiviBankingModule(
      $this->getTestResourcePath('matcher/configuration/CancelExistingContribution-01.civibanking'));

    // configure a post-processor to tag the contact
    $this->configureCiviBankingModule(
      $this->getTestResourcePath('post_processor/configuration/MarkContactDeceased.civibanking'));

    // run the matcher
    $this->runMatchers();

    // check if the contribution status has been update
    $cancelled_contribution = $this->callAPISuccess(
      'Contribution', 'getsingle', ['id' => $contribution['id']]);
    $this->assertEquals(3, $cancelled_contribution['contribution_status_id'], "Contribution wasn't cancelled.");

    // check if the postprocessor worked
    $contact = $this->callAPISuccess(
      'Contact', 'getsingle', ['id' => $cancelled_contribution['contact_id']]);
    $this->assertEquals(1, $contact['is_deceased'], "Contact was not marked as deceased");
  }
}
