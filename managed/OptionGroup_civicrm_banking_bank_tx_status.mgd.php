<?php
use CRM_Banking_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_civicrm_banking_bank_tx_status',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civicrm_banking.bank_tx_status',
        'is_active' => TRUE,
        'is_reserved' => TRUE,
        'title' => E::ts('CiviBanking bank transaction processing status'),
        'description' => E::ts('The set of possible processing statuses for a CiviBanking bank transaction'),
        'option_value_fields' => ['name', 'label', 'description'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_bank_tx_status_OptionValue_new',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.bank_tx_status',
        'label' => E::ts('New'),
        'value' => '0',
        'name' => 'new',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_bank_tx_status_OptionValue_ignored',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.bank_tx_status',
        'label' => E::ts('Ignored'),
        'value' => '1',
        'name' => 'ignored',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_bank_tx_status_OptionValue_suggestions',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.bank_tx_status',
        'label' => E::ts('Suggestions'),
        'value' => '2',
        'name' => 'suggestions',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_bank_tx_status_OptionValue_processed',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.bank_tx_status',
        'label' => E::ts('Processed'),
        'value' => '3',
        'name' => 'processed',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
