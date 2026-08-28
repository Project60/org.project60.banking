<?php
use CRM_Banking_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'civicrm_banking.reference_types',
        'is_active' => TRUE,
        'is_reserved' => TRUE,
        'title' => E::ts('CiviBanking bank account reference types'),
        'description' => E::ts('The set of possible CiviBanking bank account reference types'),
        'option_value_fields' => ['name', 'label', 'description'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_IBAN',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('International Bank Account Number'),
        'value' => 'IBAN',
        'name' => 'IBAN',
        'description' => E::ts('Format: see <a href="https://en.wikipedia.org/wiki/International_Bank_Account_Number">IBAN on WikiPedia<a>.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_DE',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('German Bank Account Number'),
        'value' => 'NBAN_DE',
        'name' => 'NBAN_DE',
        'description' => E::ts('Format is "BBBBBBBB/KKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345678/0000123456"'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_AT',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Austrian Bank Account Number'),
        'value' => 'NBAN_AT',
        'name' => 'NBAN_AT',
        'description' => E::ts('Format is "BBBBB/KKKKKKKKKKK", (B="BLZ", K="Kontonummer") eg. "12345/00001234567"'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_BE',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Belgian Bank Account Number'),
        'value' => 'NBAN_BE',
        'name' => 'NBAN_BE',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_CH',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Swiss Bank Account Number'),
        'value' => 'NBAN_CH',
        'name' => 'NBAN_CH',
        'description' => E::ts('Format is XX-XXXXXXXXX-X.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_CZ',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Czech Bank Account Number'),
        'value' => 'NBAN_CZ',
        'name' => 'NBAN_CZ',
        'description' => E::ts('Format is "PPPPPP-UUUUUUUUUU/CCCC" (P="predcisli/prefix", U="vlastni cislo/bank account", C="bank code") The first part (PPPPPP-) is optional.'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_FP',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Fingerprint'),
        'value' => 'NBAN_FP',
        'name' => 'NBAN_FP',
        'description' => E::ts('SHA1 fingerprint of some tell-tale value in the transaction information'),
        'is_reserved' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_ENTITY',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Internal Link'),
        'value' => 'ENTITY',
        'name' => 'ENTITY',
        'description' => E::ts('Links a bank account to a CiviCRM entity, reference format is "<entity_table>:<entity_id>"'),
        'is_reserved' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_GC',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('GoCardless'),
        'value' => 'NBAN_GC',
        'name' => 'NBAN_GC',
        'description' => E::ts('GoCardless customer ID'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_WP',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('WorldPay'),
        'value' => 'NBAN_WP',
        'name' => 'NBAN_WP',
        'description' => E::ts('WorldPay merchant ID'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_SP',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Stewardship'),
        'value' => 'NBAN_SP',
        'name' => 'NBAN_SP',
        'description' => E::ts('Stewardship merchant ID'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_PP',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('PayPal'),
        'value' => 'NBAN_PP',
        'name' => 'NBAN_PP',
        'description' => E::ts('PayPal account identification (email)'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_civicrm_banking_reference_types_OptionValue_NBAN_ES',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'civicrm_banking.reference_types',
        'label' => E::ts('Spanish Bank Account Number'),
        'value' => 'NBAN_ES',
        'name' => 'NBAN_ES',
        'description' => E::ts('Traditional Spanish bank account number'),
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
