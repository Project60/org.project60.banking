<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2022 SYSTOPIA                            |
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

/**
 * Helper class to mitigate some API issues
 */
class CRM_Banking_Helpers_IssueMitigation {

  /**
   * Mitigate a problem where a missing contact_id triggers a a foreign key constraint fail
   *
   * @param array $call_payload
   *   the Contribution.create API call payload to be adjusted in place
   *
   * @see https://github.com/Project60/org.project60.banking/issues/358
   */
  public static function mitigate358(&$call_payload)
  {
    if (empty($call_payload['contact_id']) && !empty($call_payload['id'])) {
      // the contribution id *should* determine the contact_id,
      //   but that seems to go wrong sometimes, so we'll add it explicitly
      $call_payload['contact_id'] = civicrm_api3('Contribution', 'getvalue', [
          'id' => $call_payload['id'],
          'return' => 'contact_id']);
    }
    return $call_payload;
  }

}