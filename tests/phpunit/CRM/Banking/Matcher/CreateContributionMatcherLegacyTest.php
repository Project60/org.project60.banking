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

declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

/**
 * @covers CRM_Banking_PluginImpl_Matcher_CreateContribution
 *
 * @group headless
 */
class CRM_Banking_CreateContributionLegacyTest extends CRM_Banking_TestBase {

  public function testSimpleCreateContributionMatcher() {
    $contactId = $this->createContact();
    $this->createTransaction(
        [
          'contact_id' => $contactId,
    // NOTE: Must be set, otherwise there occurs an error while matching CreateContribution.
          'name' => '',
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
        E::ts('The contact ID is not correct.')
    );
    $this->assertEquals(
        1,
        $contribution['payment_instrument_id'],
        E::ts('The payment instrument ID is not correct.')
    );
    $this->assertEquals(
        1,
        $contribution['financial_type_id'],
        E::ts('The financial type ID is not correct.')
    );
  }

}
