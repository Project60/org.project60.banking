<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
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
   * Create table for Rules Matcher/Analyser
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0610() {
    $this->ctx->log->info('Applying update 0610');
    $this->executeSqlFile('sql/banking.sql');

    // update rebuild log tables
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();

    return TRUE;
  }

  /**
   * When upgrading the extension keep the new UI disabled.
   * This way the new UI is only visible upon a new installation.
   *
   * Also update the order of the transaction statuses as we use the order to sort the statement lines screen.
   *
   * @return TRUE on success
   */
  public function upgrade_0611() {
    CRM_Core_BAO_Setting::setItem(false, 'org.project60.banking', 'new_ui');

    // Update order of the option group banking_tx_status.
    $statusApi = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'civicrm_banking.bank_tx_status', 'options' => array('limit' => 0)));
    $statuses = array();
    foreach($statusApi['values'] as $status) {
      $statuses[$status['name']] = $status;
    }

    // Set ignore status the weight from processed
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_value SET weight = %1 WHERE id = %2", array(
      1=> array($statuses['processed']['weight'], 'Integer'),
      2=> array($statuses['ignored']['id'], 'Integer'),
    ));
    // Set processed status the weight from suggestions
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_value SET weight = %1 WHERE id = %2", array(
      1=> array($statuses['suggestions']['weight'], 'Integer'),
      2=> array($statuses['processed']['id'], 'Integer'),
    ));
    // Set suggestions status the weight from ignored
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_value SET weight = %1 WHERE id = %2", array(
      1=> array($statuses['ignored']['weight'], 'Integer'),
      2=> array($statuses['suggestions']['id'], 'Integer'),
    ));

    // make sure the new menu is registered with the CMS
    CRM_Core_Invoke::rebuildMenuAndCaches();

    return true;
  }

  /**
   * Upgrader for new rules analyser.
   *  Also contains 2 legacy updates migrated form the _enabled hook
   *
   * @return TRUE on success
   */
  public function upgrade_0700() {
    $this->ctx->log->info('Applying update 0700');
    $this->executeSqlFile('sql/upgrade_rules.sql');

    // this is an old update that's been moved here
    $this->executeSqlFile('sql/upgrade_importer_path.sql');

    // this is an old update for https://github.com/Project60/org.project60.banking/issues/158
    $index = CRM_Core_DAO::executeQuery("SHOW INDEX FROM civicrm_bank_account_reference WHERE column_name = 'reference'");
    if (!$index->fetch()) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_bank_account_reference ADD INDEX `reference` (reference);");
    }

    // update rebuild log tables
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();

    return true;
  }

  /**
   * Upgrader for 0.7.alpha release
   *  - inject default configuration if not there
   *
   * @return TRUE on success
   */
  public function upgrade_0701() {
    $this->ctx->log->info('Applying update 0701');

    // install default configuration
    $config_exists = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_bank_plugin_instance;");
    if (!$config_exists) {
      // install all default files from the default/configuration folder
      $base_folder = E::path('default/configuration');
      foreach (scandir($base_folder) as $config_file) {
        if (preg_match("#.civibanking$#", $config_file)) {
          $data = file_get_contents($base_folder . DIRECTORY_SEPARATOR . $config_file);
          $plugin_bao = new CRM_Banking_BAO_PluginInstance();
          $plugin_bao->updateWithSerialisedData($data);
        }
      }
    }

    // remove old entries
    $removed_entries_query = civicrm_api3('OptionValue', 'get', array(
        'value'           => ['IN' => ['CRM_Banking_PluginImpl_Matcher_Generic']],
        'option_group_id' => 'civicrm_banking.plugin_types',
        'return'          => 'id'));
    foreach ($removed_entries_query['values'] as $removed_entry) {
      civicrm_api3('OptionValue', 'delete', array('id' => $removed_entry['id']));
    }

    // adjust table
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_bank_rules` CHANGE `tx_purpose` `tx_purpose` VARCHAR(255);");

    return true;
  }

  /**
   * Upgrader for 0.7.alpha3 release
   *  - update options/matchers/etc.
   *
   * @return TRUE on success
   */
  public function upgrade_0702() {
    // update option groups
    // replaced by 0703:  banking_civicrm_install_options(_banking_options());
    return true;
  }

  /**
   * Upgrader for 0.7.alpha5 release
   *  - update options/matchers/etc.
   *
   * @return TRUE on success
   */
  public function upgrade_0703() {
    // update option groups
    banking_civicrm_install_options(_banking_options());
    return true;
  }

  /**
   * Upgrader for 0.7.alpha7 release
   *
   * @return TRUE on success
   */
  public function upgrade_0704() {
    // rebuild menu, in particular for the UI
    CRM_Core_Invoke::rebuildMenuAndCaches();
    CRM_Core_BAO_Setting::setItem($values['reference_matching_probability'], 'CiviBanking', 'reference_matching_probability');
    return true;
  }

  /**
   * Upgrader for 0.8 release
   *
   * @return TRUE on success
   */
  public function upgrade_0800() {
    // Set the bank account reference probability to 100%.
    CRM_Core_BAO_Setting::setItem('1.0', 'CiviBanking', 'reference_matching_probability');
    return true;
  }

  /**
   * Upgrader for 0.8 / BANKING-313:
   *
   * Adding an index to civicrm_bank_tx.status_id
   *
   * @return TRUE on success
   */
  public function upgrade_0801() {
    // adding an index
    if (!CRM_Core_DAO::singleValueQuery("SHOW INDEX FROM civicrm_bank_tx WHERE key_name = 'FK_civicrm_bank_tx_status_id'")) {
      $this->ctx->log->info('Adding status_id index to transaction table.');
      $this->executeSql("ALTER TABLE civicrm_bank_tx ADD KEY `FK_civicrm_bank_tx_status_id`(`status_id`);");
    }
    return true;
  }

  /**
   * Upgrader for 0.8 / BANKING-323:
   *
   * Update options/matchers/etc.
   *
   * @return TRUE on success
   */
  public function upgrade_0802() {
    // update option groups
    $this->ctx->log->info('Updated options.');
    banking_civicrm_install_options(_banking_options());
    return true;
  }


  /**
   * Upgrader for 0.8 / BANKING-312:
   *
   * Add new civicrm_bank_tx_contribution table
   *  and migrate existing transactions (from json_blob)
   *
   * @return TRUE on success
   */
  public function upgrade_0804() {
    // to add the table, simply run sql schema script again
    $this->ctx->log->info('Added transaction-contribution link table.');
    $this->executeSqlFile('sql/banking.sql');

    // schedule migrating existing transactions ($batch_size at a time)
    $batch_size = 1000;
    $min_bank_tx_id = CRM_Core_DAO::singleValueQuery("SELECT MIN(id) FROM civicrm_bank_tx;");
    $max_bank_tx_id = CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_bank_tx;");
    $current_bank_tx_id = $min_bank_tx_id;
    while ($current_bank_tx_id <= $max_bank_tx_id) {
      $this->ctx->queue->createItem(
        new CRM_Banking_Helpers_ContributionLinkMigration($current_bank_tx_id, $batch_size));
      $current_bank_tx_id += $batch_size;
    }

    // update logging schema
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();

    return true;
  }


}
