<?php

use Civi\Banking\Matcher\CustomActionsMatcher;
use CRM_Banking_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civicrm_banking.plugin_types',
        'title' => E::ts('CiviBanking plugin types'),
        'description' => E::ts('The set of possible CiviBanking plugin types'),
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_importer_dummy',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Dummy Data Importer Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Dummy',
        'name' => 'importer_dummy',
        'description' => E::ts('For testing purposes only'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_importer_csv',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Configurable CSV Importer'),
        'value' => 'CRM_Banking_PluginImpl_Importer_CSV',
        'name' => 'importer_csv',
        'description' => E::ts('This importer should be configurable to import any CSV based data.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_importer_xml',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Configurable XML Importer'),
        'value' => 'CRM_Banking_PluginImpl_Importer_XML',
        'name' => 'importer_xml',
        'description' => E::ts('This importer should be configurable to import a variety of XML based data.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_importer_fixed',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Fixed Width TXT Importer'),
        'value' => 'CRM_Banking_PluginImpl_Importer_Fixed',
        'name' => 'importer_fixed',
        'description' => E::ts('This importer should be configurable for most fixed-width txt standards.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_custom_actions',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Custom Actions Matcher'),
        'value' => CustomActionsMatcher::class,
        'name' => CustomActionsMatcher::NAME,
        'description' => E::ts('Performs custom actions.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_create',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Create Contribution Matcher Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_CreateContribution',
        'name' => 'matcher_create',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_create_campaign',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Create Campaign Contribution Matcher Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_CreateCampaignContribution',
        'name' => 'matcher_create_campaign',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_create_multiple',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Create Multiple Contributions Matcher Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_CreateMultipleContributions',
        'name' => 'matcher_create_multiple',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_recurring',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Recurring Contribution Matcher Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_RecurringContribution',
        'name' => 'matcher_recurring',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_membership',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Membership Matcher Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_Membership',
        'name' => 'matcher_membership',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_yes',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Dummy Matcher Test Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_Yes',
        'name' => 'matcher_yes',
        'description' => E::ts('For testing purposes only'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_default',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Default Options Matcher'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_DefaultOptions',
        'name' => 'matcher_default',
        'description' => E::ts('Generates default options, namely "ignore" and "process manually"'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_ignore',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Ignore Matcher'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_Ignore',
        'name' => 'matcher_ignore',
        'description' => E::ts('Can be configured to ignore a transaction when certain patterns are detected.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_contribution',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Contribution Matcher'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_ExistingContribution',
        'name' => 'matcher_contribution',
        'description' => E::ts('Will match the transaction to existing (pending) contributions.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_batch',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Batch Matcher'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_Batches',
        'name' => 'matcher_batch',
        'description' => E::ts('Tries to identify transactions that settle contribution batches.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_matcher_sepa',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('SEPA Matcher'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_SepaMandate',
        'name' => 'matcher_sepa',
        'description' => E::ts('Will match SEPA DD transactions to contributions created by the org.project60.sepa module.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_analyser_regex',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('RegEx Analyser'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_RegexAnalyser',
        'name' => 'analyser_regex',
        'description' => E::ts('Analyses and enriches the transactions information using regular expressions.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_analyser_account',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Account Lookup Analyser'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_AccountLookup',
        'name' => 'analyser_account',
        'description' => 'Looks up a transaction\'s bank accounts (again)',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_analyser_rules',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Rule Analyser Plugin'),
        'value' => 'CRM_Banking_PluginImpl_Matcher_RulesAnalyser',
        'name' => 'analyser_rules',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_accounts',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Bank Accounts PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_Accounts',
        'name' => 'postprocessor_accounts',
        'description' => E::ts('Allows you to store the bank accounts with the contact or contribution'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_addressupdate',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Update Address PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_AddressUpdate',
        'name' => 'postprocessor_addressupdate',
        'description' => 'Updates a contact\'s address with the one from the transaction',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_membership_payment',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('MembershipPayment PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipPayment',
        'name' => 'postprocessor_membership_payment',
        'description' => E::ts('Assigns newly created contributions to memberships'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_api',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('API PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_API',
        'name' => 'postprocessor_api',
        'description' => E::ts('Triggers any API action'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_contact_deceased',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Contact Deceased PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_ContactDeceased',
        'name' => 'postprocessor_contact_deceased',
        'description' => E::ts('Marks a contact as "deceased"'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_recurring_fails',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Recurring Contribution Fails PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_RecurringFails',
        'name' => 'postprocessor_recurring_fails',
        'description' => E::ts('Processes contribution fails and cancellations for recurring contributions, including CiviSEPA DDs'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_postprocessor_membership_extension',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Membership Extension PostProcessor'),
        'value' => 'CRM_Banking_PluginImpl_PostProcessor_MembershipExtension',
        'name' => 'postprocessor_membership_extension',
        'description' => E::ts('Will automatically extend memberships if the right contribution is processed'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_types_OptionValue_exporter_csv',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_types',
        'label' => E::ts('Configurable CSV Exporter'),
        'value' => 'CRM_Banking_PluginImpl_Exporter_CSV',
        'name' => 'exporter_csv',
        'description' => E::ts('This exporter should be configurable to export paymetns to a CSV format.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
