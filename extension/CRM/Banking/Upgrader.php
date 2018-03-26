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

use CRM_Banking_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Banking_Upgrader extends CRM_Banking_Upgrader_Base {

  /**
   * Upgrade account table
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0070() {
    $this->ctx->log->info('Applying update 0.7');
    // ADD is_active column
    $existing_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_bank_account` LIKE 'is_active';");
    if (!$existing_column) {
      // add new column
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_bank_account` ADD COLUMN `is_active` TINYINT DEFAULT 1 COMMENT "defines a BA as the default";');

      // mark all as active
      CRM_Core_DAO::executeQuery('UPDATE `civicrm_bank_account` SET `is_active` = 1;');

      // add index
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_bank_account` ADD INDEX is_active (is_active);');
    }

    // ADD is_primary column
    $existing_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_bank_account` LIKE 'is_primary';");
    if (!$existing_column) {
      // add new column
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_bank_account` ADD COLUMN `is_primary` TINYINT NOT NULL COMMENT "if disabled, a BA should not be used any more";');

      // mark the most recently used account as default
      // TODO: implement

      // add index
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_bank_account` ADD INDEX is_primary (is_primary);');
    }

    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  }
}
