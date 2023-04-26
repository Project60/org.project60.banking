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
   * Setting for the transaction list cutoff
   */
  const SETTING_TRANSACTION_LIST_CUTOFF = 'transaction_list_cutoff';

  /**
   * Should the bank account dedupe be done in a lenient way?
   *
   * @return boolean
   */
  public static function lenientDedupe() {
    $value = CRM_Core_BAO_Setting::getItem('CiviBanking', 'lenient_dedupe');
    if (empty($value)) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * The maximum amount of transaction to be shown on the screen
   *
   * @return integer
   */
  public static function transactionViewCutOff() {
    $cutoff = (int) Civi::settings()->get(CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF);
    if (empty($cutoff)) {
      $cutoff = 2000;
    }
    return $cutoff;
  }

  /**
   * Return a sql INTERVAL expression to cut off the completed transactions horizon
   *
   * @return string
   *   SQL interval expression
   */
  public static function getRecentlyCompletedStatementCutoff() : string
  {
    $config_setting = (int) Civi::settings()->get('recently_completed_cutoff');
    if (!empty($config_setting)) {
      return "INTERVAL {$config_setting} MONTH";
    } else {
      return '';
    }
  }
}
