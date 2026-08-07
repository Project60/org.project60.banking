<?php

use CRM_Banking_ExtensionUtil as E;

return [
  'banking_importer' => [
    'name' => 'banking_importer',
    'type' => 'String',
    'html_type' => 'select',
    'options' => [
      'standard' => E::ts('Standard'),
      'quick' => E::ts('Quick'),
      //'both' => E::ts('Both'),
    ],
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Importer'),
    'description' => E::ts('Choose to use the standard importer or the "quick" importer.'),
  ],
];
