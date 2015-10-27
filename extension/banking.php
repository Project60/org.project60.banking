<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

require_once 'banking.civix.php';
require_once 'hooks.php';

/**
 * Implementation of hook_civicrm_config
 */
function banking_civicrm_config(&$config) {
  _banking_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function banking_civicrm_xmlMenu(&$files) {
  _banking_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function banking_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sql = file_get_contents(dirname(__FILE__) . '/sql/banking.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

  //add the required option groups
  banking_civicrm_install_options(banking_civicrm_options());

  return _banking_civix_civicrm_install();
}

function banking_civicrm_install_options($data) {
  foreach ($data as $groupName => $group) {
    // check group existence
    $result = civicrm_api('option_group', 'getsingle', array('version' => 3, 'name' => $groupName));
    if (isset($result['is_error']) && $result['is_error']) {
      $params = array(
          'version' => 3,
          'sequential' => 1,
          'name' => $groupName,
          'is_reserved' => 1,
          'is_active' => 1,
          'title' => $group['title'],
          'description' => $group['description'],
      );
      $result = civicrm_api('option_group', 'create', $params);
      $group_id = $result['values'][0]['id'];
    } else
      $group_id = $result['id'];

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 1;
      //print_r(array_keys($groupValues));
      foreach ($groupValues as $valueName => $value) {
        $result = civicrm_api('option_value', 'getsingle', array('version' => 3, 'name' => $valueName));
        if (isset($result['is_error']) && $result['is_error']) {
          $params = array(
              'version' => 3,
              'sequential' => 1,
              'option_group_id' => $group_id,
              'name' => $valueName,
              'label' => $value['label'],
              'value' => $value['value'],
              'weight' => $weight,
              'is_default' => $value['is_default'],
              'is_active' => 1,
          );
          $result = civicrm_api('option_value', 'create', $params);
        } else {
          $weight = $result['weight'] + 1;
        }
      }
    }
  }
}

function banking_civicrm_options() {
  // start with the lowest weight value
  return array(
      'civicrm_banking.plugin_types' => array(
          'title' => 'CiviBanking plugin types',
          'description' => 'The set of possible CiviBanking plugin types',
          'values' => array(
              'importer_dummy' => array(
                  'label' => 'Dummy Data Importer Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Dummy',
                  'description' => 'For testing purposes only',
                  'is_default' => 0,
              ),
              'importer_csv' => array(
                  'label' => 'Configurable CSV Importer',
                  'value' => 'CRM_Banking_PluginImpl_Importer_CSV',
                  'description' => 'This importer should be configurable to import any CSV based data.',
                  'is_default' => 0,
              ),
              'importer_xml' => array(
                  'label' => 'Configurable XML Importer',
                  'value' => 'CRM_Banking_PluginImpl_Importer_XML',
                  'description' => 'This importer should be configurable to import a variety of XML based data.',
                  'is_default' => 0,
              ),
              'matcher_generic' => array(
                  'label' => 'Generic Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_Generic',
                  'is_default' => 0,
              ),
              'matcher_create' => array(
                  'label' => 'Create Contribution Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_CreateContribution',
                  'is_default' => 0,
              ),
              'matcher_recurring' => array(
                  'label' => 'Recurring Contribution Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_RecurringContribution',
                  'is_default' => 0,
              ),
              'matcher_membership' => array(
                  'label' => 'Membership Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_Membership',
                  'is_default' => 0,
              ),
              'matcher_yes' => array(
                  'label' => 'Dummy Matcher Test Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_Yes',
                  'description' => 'For testing purposes only',
                  'is_default' => 0,
              ),
              'matcher_default' => array(
                  'label' => 'Default Options Matcher',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_DefaultOptions',
                  'description' => 'Generates default options, namely "ignore" and "process manually"',
                  'is_default' => 0,
              ),
              'matcher_ignore' => array(
                  'label' => 'Ignore Matcher',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_Ignore',
                  'description' => 'Can be configured to ignore a transaction when certain patterns are detected.',
                  'is_default' => 0,
              ),
              'matcher_contribution' => array(
                  'label' => 'Contribution Matcher',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_ExistingContribution',
                  'description' => 'Will match the transaction to existing (pending) contributions.',
                  'is_default' => 0,
              ),
              'matcher_batch' => array(
                  'label' => 'Batch Matcher',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_Batches',
                  'description' => 'Tries to identify transactions that settle contribution batches.',
                  'is_default' => 0,
              ),
              'matcher_sepa' => array(
                  'label' => 'SEPA Matcher',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_SepaMandate',
                  'description' => 'Will match SEPA DD transactions to contributions created by the org.project60.sepa module.',
                  'is_default' => 0,
              ),
              'analyser_regex' => array(
                  'label' => 'RegEx Analyser',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_RegexAnalyser',
                  'description' => 'Analyses and enriches the transactions information using regular expressions.',
                  'is_default' => 0,
              ),
              'analyser_account' => array(
                  'label' => 'Account Lookup Analyser',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_AccountLookup',
                  'description' => 'Looks up a transaction\'s bank accounts (again)',
                  'is_default' => 0,
              ),
              'exporter_csv' => array(
                  'label' => 'Configurable CSV Exporter',
                  'value' => 'CRM_Banking_PluginImpl_Exporter_CSV',
                  'description' => 'This exporter should be configurable to export paymetns to a CSV format.',
                  'is_default' => 0,
              ),
          ),
      ),
      'civicrm_banking.reference_types' => array(
          'title' => 'CiviBanking bank account reference types',
          'description' => 'The set of possible CiviBanking bank account reference types',
          'values' => array(
              'IBAN' => array(
                  'label' => ts('International Bank Account Number'),
                  'value' => 'IBAN',
                  'description' => ts('See https://en.wikipedia.org/wiki/International_Bank_Account_Number'),
                  'is_default' => 1,
              ),
              'NBAN_DE' => array(
                  'label' => ts('German Bank Account Number'),
                  'value' => 'NBAN_DE',
                  'description' => ts('Format is XXXXXXXX/XXXXXXXXXX (BLZ/Kontonummer), eg. "12345678/0000123456"'),
                  'is_default' => 0,
              ),
              'NBAN_AT' => array(
                  'label' => ts('Austrian Bank Account Number'),
                  'value' => 'NBAN_AT',
                  'is_default' => 0,
              ),
              'NBAN_BE' => array(
                  'label' => ts('Belgian Bank Account Number'),
                  'value' => 'NBAN_BE',
                  'is_default' => 0,
              ),
              'NBAN_CH' => array(
                  'label' => ts('Swiss Bank Account Number'),
                  'value' => 'NBAN_CH',
                  'description' => ts('Format is XX-XXXXXXXXX-X.'),
                  'is_default' => 0,
              ),
              'NBAN_CZ' => array(
                  'label' => ts('Czech Bank Account Number'),
                  'value' => 'NBAN_CZ',
                  'description' => ts('Format is "prefix-account_number/bank_code{4}". The first part (prefix-) is optional.'),
                  'is_default' => 0,
              ),
              'NBAN_FP' => array(
                  'label' => ts('Fingerprint'),
                  'value' => 'NBAN_FP',
                  'description' => ts('SHA1 fingerprint of some tell-tale value in the transaction information'),
                  'is_default' => 0,
              ),
              'NBAN_GC' => array(
                  'label' => ts('GoCardless'),
                  'value' => 'NBAN_GC',
                  'description' => ts('GoCardless customer ID'),
                  'is_default' => 0,
              ),
              'NBAN_WP' => array(
                  'label' => ts('WorldPay'),
                  'value' => 'NBAN_WP',
                  'description' => ts('WorldPay merchant ID'),
                  'is_default' => 0,
              ),
              'NBAN_PP' => array(
                  'label' => ts('PayPal'),
                  'value' => 'NBAN_PP',
                  'description' => ts('PayPal account identification (email)'),
                  'is_default' => 0,
              ),
              'ENTITY' => array(
                  'label' => ts('Links a bank account to a CiviCRM entity, reference format is "<entity_table>:<entity_id>"'),
                  'value' => 'ENTITY',
                  'is_default' => 0,
              ),
          ),
      ),
      'civicrm_banking.plugin_classes' => array(
          'title' => 'CiviBanking plugin classes',
          'description' => 'The set of existing CiviBanking plugin types',
          'values' => array(
              'import' => array(
                  'label' => 'Import plugin',
                  'value' => 1,
                  'is_default' => 0,
              ),
              'match' => array(
                  'label' => 'Match plugin',
                  'value' => 2,
                  'is_default' => 0,
              ),
              'export' => array(
                  'label' => 'Export plugin',
                  'value' => 3,
                  'is_default' => 0,
              ),
          ),
      ),
      'civicrm_banking.bank_tx_status' => array(
          'title' => 'CiviBanking bank transaction processing status',
          'description' => 'The set of possible processing statuses for a CiviBanking bank transaction',
          'values' => array(
              'new' => array(
                  'label' => 'New',
                  'value' => 0,
                  'is_default' => 1,
              ),
              'ignored' => array(
                  'label' => 'Ignored',
                  'value' => 1,
                  'is_default' => 0,
              ),
              'suggestions' => array(
                  'label' => 'Suggestions',
                  'value' => 2,
                  'is_default' => 0,
              ),
              'processed' => array(
                  'label' => 'Processed',
                  'value' => 3,
                  'is_default' => 0,
              ),
          ),
      ),
  );
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function banking_civicrm_uninstall() {
  return _banking_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function banking_civicrm_enable() {
  //add the required option groups
  banking_civicrm_install_options(banking_civicrm_options());

  // run the update script
  $config = CRM_Core_Config::singleton();
  $sql = file_get_contents(dirname(__FILE__) . '/sql/upgrade.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

  return _banking_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function banking_civicrm_disable() {
  return _banking_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function banking_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _banking_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function banking_civicrm_managed(&$entities) {
  return _banking_civix_civicrm_managed($entities);
}

