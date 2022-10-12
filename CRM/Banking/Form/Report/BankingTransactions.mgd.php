<?php

use CRM_Banking_ExtensionUtil as E;

// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return array (
  0 =>
  array (
    'name' => 'CRM_Banking_Form_Report_BankingTransactions',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => E::ts('Banking Transactions'),
      'description' => E::ts('List all CiviBankiong transactions. Additional filtering is possible on booking and value date and on status.'),
      'class_name' => 'CRM_Banking_Form_Report_BankingTransactions',
      'report_url' => 'org.project60.banking/transactions',
      'component' => 'CiviContribute',
    ),
  ),
);
