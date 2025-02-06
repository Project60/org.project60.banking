<?php
declare(strict_types = 1);

use CRM_Banking_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_banking_transaction_domain',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'banking_transaction_domain',
        'title' => E::ts('CiviBanking Transaction Domain'),
        'description' => E::ts('Domains for CiviBanking transactions.'),
        'data_type' => 'String',
        'is_reserved' => FALSE,
        'is_active' => TRUE,
        'is_locked' => FALSE,
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
    ],
    'match' => [
      'name',
    ],
  ],
];
