<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
 * define CiviBanking option values
 */
function _banking_options() {
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
              'importer_fixed' => array(
                  'label' => 'Fixed Width TXT Importer',
                  'value' => 'CRM_Banking_PluginImpl_Importer_Fixed',
                  'description' => 'This importer should be configurable for most fixed-width txt standards.',
                  'is_default' => 0,
              ),
              'matcher_create' => array(
                  'label' => 'Create Contribution Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_CreateContribution',
                  'is_default' => 0,
              ),
              'matcher_create_multiple' => array(
                  'label' => 'Create Multiple Contributions Matcher Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_CreateMultipleContributions',
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
              'analyser_rules' => array(
                  'label' => 'Rule Analyser Plugin',
                  'value' => 'CRM_Banking_PluginImpl_Matcher_RulesAnalyser',
                  'is_default' => 0,
              ),
              'postprocessor_accounts' => array(
                  'label' => 'Bank Accounts PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_Accounts',
                  'description' => 'Allows you to store the bank accounts with the contact or contribution',
                  'is_default' => 0,
              ),
              'postprocessor_addressupdate' => array(
                  'label' => 'Update Address PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_AddressUpdate',
                  'description' => 'Updates a contact\'s address with the one from the transaction',
                  'is_default' => 0,
              ),
              'postprocessor_membership_payment' => array(
                  'label' => 'MembershipPayment PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipPayment',
                  'description' => 'Assigns newly created contributions to memberships',
                  'is_default' => 0,
              ),
              'postprocessor_api' => array(
                  'label' => 'API PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_API',
                  'description' => 'Triggers any API action',
                  'is_default' => 0,
              ),
              'postprocessor_contact_deceased' => array(
                  'label' => 'Contact Deceased PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_ContactDeceased',
                  'description' => 'Marks a contact as "deceased"',
                  'is_default' => 0,
              ),
              'postprocessor_recurring_fails' => array(
                  'label' => 'Recurring Contribution Fails PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_RecurringFails',
                  'description' => 'Processes contribution fails and cancellations for recurring contributions, including CiviSEPA DDs',
                  'is_default' => 0,
              ),
              'postprocessor_membership_extension' => array(
                  'label' => 'Membership Extension PostProcessor',
                  'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipExtension',
                  'description' => 'Will automatically extend memberships if the right contribution is processed',
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
                  'label' => E::ts('International Bank Account Number'),
                  'value' => 'IBAN',
                  'description' => E::ts('Format: see <a href="https://en.wikipedia.org/wiki/International_Bank_Account_Number">IBAN on WikiPedia<a>.'),
                  'is_default' => 1,
              ),
              'NBAN_DE' => array(
                  'label' => E::ts('German Bank Account Number'),
                  'value' => 'NBAN_DE',
                  'description' => E::ts('Format is "BBBBBBBB/KKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345678/0000123456"'),
                  'is_default' => 0,
              ),
              'NBAN_AT' => array(
                  'label' => E::ts('Austrian Bank Account Number'),
                  'value' => 'NBAN_AT',
                  'description' => E::ts('Format is "BBBBB/KKKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345/00001234567"'),
                  'is_default' => 0,
              ),
              'NBAN_BE' => array(
                  'label' => E::ts('Belgian Bank Account Number'),
                  'value' => 'NBAN_BE',
                  'is_default' => 0,
              ),
              'NBAN_CH' => array(
                  'label' => E::ts('Swiss Bank Account Number'),
                  'value' => 'NBAN_CH',
                  'description' => E::ts('Format is XX-XXXXXXXXX-X.'),
                  'is_default' => 0,
              ),
              'NBAN_CZ' => array(
                  'label' => E::ts('Czech Bank Account Number'),
                  'value' => 'NBAN_CZ',
                  'description' => E::ts('Format is "PPPPPP-UUUUUUUUUU/CCCC" (P="predcisli/prefix", U="vlastni cislo/bank account", C="bank code") The first part (PPPPPP-) is optional.'),
                  'is_default' => 0,
              ),
              'NBAN_FP' => array(
                  'label' => E::ts('Fingerprint'),
                  'value' => 'NBAN_FP',
                  'description' => E::ts('SHA1 fingerprint of some tell-tale value in the transaction information'),
                  'is_reserved' => 1,
                  'is_default' => 0,
              ),
              'ENTITY' => array(
                  'label' => E::ts('Internal Link'),
                  'value' => 'ENTITY',
                  'description' => 'Links a bank account to a CiviCRM entity, reference format is "<entity_table>:<entity_id>"',
                  'is_reserved' => 1,
                  'is_default' => 0,
              ),
              'NBAN_GC' => array(
                  'label' => E::ts('GoCardless'),
                  'value' => 'NBAN_GC',
                  'description' => E::ts('GoCardless customer ID'),
                  'is_default' => 0,
              ),
              'NBAN_WP' => array(
                  'label' => E::ts('WorldPay'),
                  'value' => 'NBAN_WP',
                  'description' => E::ts('WorldPay merchant ID'),
                  'is_default' => 0,
              ),
              'NBAN_SP' => array(
                  'label' => E::ts('Stewardship'),
                  'value' => 'NBAN_SP',
                  'description' => E::ts('Stewardship merchant ID'),
                  'is_default' => 0,
              ),
              'NBAN_PP' => array(
                  'label' => E::ts('PayPal'),
                  'value' => 'NBAN_PP',
                  'description' => E::ts('PayPal account identification (email)'),
                  'is_default' => 0,
              ),
              'NBAN_ES' => array(
                  'label' => E::ts('Spanish Bank Account Number'),
                  'value' => 'NBAN_ES',
                  'description' => E::ts('Traditional Spanish bank account number'),
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
              'postprocess' => array(
                  'label' => 'Post Processor',
                  'value' => 4,
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
              'ignored' => array(
                  'label' => 'Ignored',
                  'value' => 1,
                  'is_default' => 0,
              ),
          ),
      ),
  );
}

/**
 * Install/Update the option values
 */
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
    } else {
      $group_id = $result['id'];
    }


    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 10;
      foreach ($groupValues as $valueName => $value) {
        // find option value
        $result = civicrm_api3('OptionValue', 'get', array(
          'name'            => $valueName,
          'option_group_id' => $group_id
          ));
        if (count($result['values']) == 0) {
          // create a new entry
          $params = array();
          $params['option_group_id'] = $group_id;
          $params['name']            = $valueName;
          $params['is_active']       = 1;
          $params['weight']          = $weight;
          $weight += 10;
        } else {
          // update existing entry
          $params = reset($result['values']); // update
        }

        $fields = array('label', 'value', 'description', 'is_default', 'is_reserved');
        foreach ($fields as $field) {
          if (isset($value[$field])) {
            $params[$field] = $value[$field];
          }
        }
        $result = civicrm_api3('option_value', 'create', $params);
      }
    }
  }
}