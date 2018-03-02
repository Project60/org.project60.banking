<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
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

/**
 * CiviBanking configuration options
 */
class CRM_Banking_Config {

  /**
   * Should the bank account dedupe be done in a lenient way?
   */
  public static function lenientDedupe() {
    $value = CRM_Core_BAO_Setting::getItem('CiviBanking', 'lenient_dedupe');
    if (empty($value)) {
      return FALSE;
    } else {
      return TRUE;
    }
  }
}