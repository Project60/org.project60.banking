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

declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

/**
 * define CiviBanking option values
 */
function _banking_options() {
  // start with the lowest weight value
  return [
    'civicrm_banking.plugin_types' => [
      'title' => 'CiviBanking plugin types',
      'description' => 'The set of possible CiviBanking plugin types',
      'values' => [
        'importer_dummy' => [
          'label' => 'Dummy Data Importer Plugin',
          'value' => 'CRM_Banking_PluginImpl_Dummy',
          'description' => 'For testing purposes only',
          'is_default' => 0,
        ],
        'importer_csv' => [
          'label' => 'Configurable CSV Importer',
          'value' => 'CRM_Banking_PluginImpl_Importer_CSV',
          'description' => 'This importer should be configurable to import any CSV based data.',
          'is_default' => 0,
        ],
        'importer_xml' => [
          'label' => 'Configurable XML Importer',
          'value' => 'CRM_Banking_PluginImpl_Importer_XML',
          'description' => 'This importer should be configurable to import a variety of XML based data.',
          'is_default' => 0,
        ],
        'importer_fixed' => [
          'label' => 'Fixed Width TXT Importer',
          'value' => 'CRM_Banking_PluginImpl_Importer_Fixed',
          'description' => 'This importer should be configurable for most fixed-width txt standards.',
          'is_default' => 0,
        ],
        'matcher_create' => [
          'label' => 'Create Contribution Matcher Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_CreateContribution',
          'is_default' => 0,
        ],
        'matcher_create_campaign' => [
          'label' => 'Create Campaign Contribution Matcher Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_CreateCampaignContribution',
          'is_default' => 0,
        ],
        'matcher_create_multiple' => [
          'label' => 'Create Multiple Contributions Matcher Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_CreateMultipleContributions',
          'is_default' => 0,
        ],
        'matcher_recurring' => [
          'label' => 'Recurring Contribution Matcher Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_RecurringContribution',
          'is_default' => 0,
        ],
        'matcher_membership' => [
          'label' => 'Membership Matcher Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_Membership',
          'is_default' => 0,
        ],
        'matcher_yes' => [
          'label' => 'Dummy Matcher Test Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_Yes',
          'description' => 'For testing purposes only',
          'is_default' => 0,
        ],
        'matcher_default' => [
          'label' => 'Default Options Matcher',
          'value' => 'CRM_Banking_PluginImpl_Matcher_DefaultOptions',
          'description' => 'Generates default options, namely "ignore" and "process manually"',
          'is_default' => 0,
        ],
        'matcher_ignore' => [
          'label' => 'Ignore Matcher',
          'value' => 'CRM_Banking_PluginImpl_Matcher_Ignore',
          'description' => 'Can be configured to ignore a transaction when certain patterns are detected.',
          'is_default' => 0,
        ],
        'matcher_contribution' => [
          'label' => 'Contribution Matcher',
          'value' => 'CRM_Banking_PluginImpl_Matcher_ExistingContribution',
          'description' => 'Will match the transaction to existing (pending) contributions.',
          'is_default' => 0,
        ],
        'matcher_batch' => [
          'label' => 'Batch Matcher',
          'value' => 'CRM_Banking_PluginImpl_Matcher_Batches',
          'description' => 'Tries to identify transactions that settle contribution batches.',
          'is_default' => 0,
        ],
        'matcher_sepa' => [
          'label' => 'SEPA Matcher',
          'value' => 'CRM_Banking_PluginImpl_Matcher_SepaMandate',
          'description' => 'Will match SEPA DD transactions to contributions created by the org.project60.sepa module.',
          'is_default' => 0,
        ],
        'analyser_regex' => [
          'label' => 'RegEx Analyser',
          'value' => 'CRM_Banking_PluginImpl_Matcher_RegexAnalyser',
          'description' => 'Analyses and enriches the transactions information using regular expressions.',
          'is_default' => 0,
        ],
        'analyser_account' => [
          'label' => 'Account Lookup Analyser',
          'value' => 'CRM_Banking_PluginImpl_Matcher_AccountLookup',
          'description' => 'Looks up a transaction\'s bank accounts (again)',
          'is_default' => 0,
        ],
        'analyser_rules' => [
          'label' => 'Rule Analyser Plugin',
          'value' => 'CRM_Banking_PluginImpl_Matcher_RulesAnalyser',
          'is_default' => 0,
        ],
        'postprocessor_accounts' => [
          'label' => 'Bank Accounts PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_Accounts',
          'description' => 'Allows you to store the bank accounts with the contact or contribution',
          'is_default' => 0,
        ],
        'postprocessor_addressupdate' => [
          'label' => 'Update Address PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_AddressUpdate',
          'description' => 'Updates a contact\'s address with the one from the transaction',
          'is_default' => 0,
        ],
        'postprocessor_membership_payment' => [
          'label' => 'MembershipPayment PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipPayment',
          'description' => 'Assigns newly created contributions to memberships',
          'is_default' => 0,
        ],
        'postprocessor_api' => [
          'label' => 'API PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_API',
          'description' => 'Triggers any API action',
          'is_default' => 0,
        ],
        'postprocessor_contact_deceased' => [
          'label' => 'Contact Deceased PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_ContactDeceased',
          'description' => 'Marks a contact as "deceased"',
          'is_default' => 0,
        ],
        'postprocessor_recurring_fails' => [
          'label' => 'Recurring Contribution Fails PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_RecurringFails',
          'description' => 'Processes contribution fails and cancellations for recurring contributions, including CiviSEPA DDs',
          'is_default' => 0,
        ],
        'postprocessor_membership_extension' => [
          'label' => 'Membership Extension PostProcessor',
          'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipExtension',
          'description' => 'Will automatically extend memberships if the right contribution is processed',
          'is_default' => 0,
        ],
        'exporter_csv' => [
          'label' => 'Configurable CSV Exporter',
          'value' => 'CRM_Banking_PluginImpl_Exporter_CSV',
          'description' => 'This exporter should be configurable to export paymetns to a CSV format.',
          'is_default' => 0,
        ],
      ],
    ],
    'civicrm_banking.reference_types' => [
      'title' => 'CiviBanking bank account reference types',
      'description' => 'The set of possible CiviBanking bank account reference types',
      'values' => [
        'IBAN' => [
          'label' => E::ts('International Bank Account Number'),
          'value' => 'IBAN',
          'description' => E::ts('Format: see <a href="https://en.wikipedia.org/wiki/International_Bank_Account_Number">IBAN on WikiPedia<a>.'),
          'is_default' => 1,
        ],
        'NBAN_DE' => [
          'label' => E::ts('German Bank Account Number'),
          'value' => 'NBAN_DE',
          'description' => E::ts('Format is "BBBBBBBB/KKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345678/0000123456"'),
          'is_default' => 0,
        ],
        'NBAN_AT' => [
          'label' => E::ts('Austrian Bank Account Number'),
          'value' => 'NBAN_AT',
          'description' => E::ts('Format is "BBBBB/KKKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345/00001234567"'),
          'is_default' => 0,
        ],
        'NBAN_BE' => [
          'label' => E::ts('Belgian Bank Account Number'),
          'value' => 'NBAN_BE',
          'is_default' => 0,
        ],
        'NBAN_CH' => [
          'label' => E::ts('Swiss Bank Account Number'),
          'value' => 'NBAN_CH',
          'description' => E::ts('Format is XX-XXXXXXXXX-X.'),
          'is_default' => 0,
        ],
        'NBAN_CZ' => [
          'label' => E::ts('Czech Bank Account Number'),
          'value' => 'NBAN_CZ',
          'description' => E::ts('Format is "PPPPPP-UUUUUUUUUU/CCCC" (P="predcisli/prefix", U="vlastni cislo/bank account", C="bank code") The first part (PPPPPP-) is optional.'),
          'is_default' => 0,
        ],
        'NBAN_FP' => [
          'label' => E::ts('Fingerprint'),
          'value' => 'NBAN_FP',
          'description' => E::ts('SHA1 fingerprint of some tell-tale value in the transaction information'),
          'is_reserved' => 1,
          'is_default' => 0,
        ],
        'ENTITY' => [
          'label' => E::ts('Internal Link'),
          'value' => 'ENTITY',
          'description' => 'Links a bank account to a CiviCRM entity, reference format is "<entity_table>:<entity_id>"',
          'is_reserved' => 1,
          'is_default' => 0,
        ],
        'NBAN_GC' => [
          'label' => E::ts('GoCardless'),
          'value' => 'NBAN_GC',
          'description' => E::ts('GoCardless customer ID'),
          'is_default' => 0,
        ],
        'NBAN_WP' => [
          'label' => E::ts('WorldPay'),
          'value' => 'NBAN_WP',
          'description' => E::ts('WorldPay merchant ID'),
          'is_default' => 0,
        ],
        'NBAN_SP' => [
          'label' => E::ts('Stewardship'),
          'value' => 'NBAN_SP',
          'description' => E::ts('Stewardship merchant ID'),
          'is_default' => 0,
        ],
        'NBAN_PP' => [
          'label' => E::ts('PayPal'),
          'value' => 'NBAN_PP',
          'description' => E::ts('PayPal account identification (email)'),
          'is_default' => 0,
        ],
        'NBAN_ES' => [
          'label' => E::ts('Spanish Bank Account Number'),
          'value' => 'NBAN_ES',
          'description' => E::ts('Traditional Spanish bank account number'),
          'is_default' => 0,
        ],
      ],
    ],
    'civicrm_banking.plugin_classes' => [
      'title' => 'CiviBanking plugin classes',
      'description' => 'The set of existing CiviBanking plugin types',
      'values' => [
        'import' => [
          'label' => 'Import plugin',
          'value' => 1,
          'is_default' => 0,
        ],
        'match' => [
          'label' => 'Match plugin',
          'value' => 2,
          'is_default' => 0,
        ],
        'postprocess' => [
          'label' => 'Post Processor',
          'value' => 4,
          'is_default' => 0,
        ],
        'export' => [
          'label' => 'Export plugin',
          'value' => 3,
          'is_default' => 0,
        ],
      ],
    ],
    'civicrm_banking.bank_tx_status' => [
      'title' => 'CiviBanking bank transaction processing status',
      'description' => 'The set of possible processing statuses for a CiviBanking bank transaction',
      'values' => [
        'new' => [
          'label' => 'New',
          'value' => 0,
          'is_default' => 1,
        ],
        'suggestions' => [
          'label' => 'Suggestions',
          'value' => 2,
          'is_default' => 0,
        ],
        'processed' => [
          'label' => 'Processed',
          'value' => 3,
          'is_default' => 0,
        ],
        'ignored' => [
          'label' => 'Ignored',
          'value' => 1,
          'is_default' => 0,
        ],
      ],
    ],
  ];
}

/**
 * Install/Update the option values
 */
function banking_civicrm_install_options($data) {
  foreach ($data as $groupName => $group) {
    // check group existence
    $result = civicrm_api3('option_group', 'getsingle', ['name' => $groupName]);
    if (isset($result['is_error']) && $result['is_error']) {
      $params = [
        'sequential' => 1,
        'name' => $groupName,
        'is_reserved' => 1,
        'is_active' => 1,
        'title' => $group['title'],
        'description' => $group['description'],
      ];
      $result = civicrm_api3('option_group', 'create', $params);
      $group_id = $result['values'][0]['id'];
    }
    else {
      $group_id = $result['id'];
    }

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 10;
      foreach ($groupValues as $valueName => $value) {
        // find option value
        $result = civicrm_api3('OptionValue', 'get', [
          'name'            => $valueName,
          'option_group_id' => $group_id,
        ]);
        if (count($result['values']) == 0) {
          // create a new entry
          $params = [];
          $params['option_group_id'] = $group_id;
          $params['name']            = $valueName;
          $params['is_active']       = 1;
          $params['weight']          = $weight;
          $weight += 10;
        }
        else {
          // update existing entry
          // update
          $params = reset($result['values']);
        }

        $fields = ['label', 'value', 'description', 'is_default', 'is_reserved'];
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
