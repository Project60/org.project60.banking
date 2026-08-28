<?php
use CRM_Banking_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_classes',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civicrm_banking.plugin_classes',
        'is_active' => TRUE,
        'is_reserved' => TRUE,
        'title' => E::ts('CiviBanking plugin classes'),
        'description' => E::ts('The set of existing CiviBanking plugin types'),
        'option_value_fields' => ['name', 'label', 'description'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_classes_OptionValue_import',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_classes',
        'label' => E::ts('Import plugin'),
        'value' => '1',
        'name' => 'import',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_classes_OptionValue_match',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_classes',
        'label' => E::ts('Match plugin'),
        'value' => '2',
        'name' => 'match',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_classes_OptionValue_postprocess',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_classes',
        'label' => E::ts('Post Processor'),
        'value' => '4',
        'name' => 'postprocess',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_plugin_classes_OptionValue_export',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.plugin_classes',
        'label' => E::ts('Export plugin'),
        'value' => '3',
        'name' => 'export',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
