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
 * CiviBanking hooks
 */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function banking_civicrm_navigationMenu(&$menu) {
  // check if we want the menu to be built at all
  $menu_position = (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'menu_position');
  switch ($menu_position) {
    case 2:
      // menu is off, see CRM_Admin_Form_Setting_BankingSettings
      $separator = 0;
      return;

    default:
    case 0:
      // top level menu
      $anchor = NULL;
      $separator = 0;
      break;

    case 1:
      // contribution menu
      $anchor = 'Contributions/';
      $separator = 1;
      break;
  }

  // Determine the url for the statements/payments (new ui or old ui).
  $statementUrl = 'civicrm/banking/statements';
  if (!CRM_Core_BAO_Setting::getItem('CiviBanking', 'new_ui')) {
    $statementUrl = 'civicrm/banking/payments';
  }

  _banking_civix_insert_navigation_menu($menu, $anchor, array(
      'label'      => E::ts('CiviBanking'),
      'name'       => 'CiviBanking',
      'icon'       => (version_compare(CRM_Utils_System::version(), '5.6', '>=')) ? 'fa fa-btc' : '',
      'permission' => 'access CiviContribute',
      'operator'   => 'OR',
      'separator'  => $separator,
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Dashboard'),
      'name'       => 'Dashboard',
      'url'        => 'civicrm/banking/dashboard',
      'permission' => 'access CiviContribute',
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Import Transactions'),
      'name'       => 'Import Transactions',
      'url'        => 'civicrm/banking/import',
      'permission' => 'access CiviContribute',
      'separator'  => 1,
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
    'label'      => E::ts('Show Transactions'),
    'name'       => 'Transactions',
    'url'        => $statementUrl,
    'permission' => 'access CiviContribute',
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Find Transactions'),
      'name'       => 'Find Transactions',
      'url'        => 'civicrm/banking/statements/search',
      'permission' => 'access CiviContribute',
      'separator'  => 1,
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Find Accounts'),
      'name'       => 'Find Accounts',
      'url'        => 'civicrm/banking/search',
      'permission' => 'access CiviContribute',
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Dedupe Accounts'),
      'name'       => 'Dedupe Accounts',
      'url'        => 'civicrm/banking/dedupe',
      'permission' => 'access CiviContribute',
      'separator'  => 1,
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
      'label'      => E::ts('Configuration Manager'),
      'name'       => 'CiviBanking Configuration',
      'url'        => 'civicrm/banking/manager',
      'permission' => 'administer CiviCRM',
  ));

  _banking_civix_insert_navigation_menu($menu, "{$anchor}CiviBanking", array(
    'label'      => E::ts('CiviBanking Settings'),
    'name'       => 'Settings',
    'url'        => 'civicrm/admin/setting/banking?reset=1',
    'permission' => 'administer CiviCRM',
  ));

  _banking_civix_navigationMenu($menu);
}


function banking_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
  $entityTypes[] = array(
      'name' => 'BankAccount',
      'class' => 'CRM_Banking_DAO_BankAccount',
      'table' => 'civicrm_bank_account',
  );
  $entityTypes[] = array(
      'name' => 'BankAccountReference',
      'class' => 'CRM_Banking_DAO_BankAccountReference',
      'table' => 'civicrm_bank_account_reference',
  );
  $entityTypes[] = array(
      'name' => 'BankTransaction',
      'class' => 'CRM_Banking_DAO_BankTransaction',
      'table' => 'civicrm_bank_tx',
  );
  $entityTypes[] = array(
      'name' => 'BankTransactionBatch',
      'class' => 'CRM_Banking_DAO_BankTransactionBatch',
      'table' => 'civicrm_bank_tx_batch',
  );
  $entityTypes[] = array(
      'name' => 'PluginInstance',
      'class' => 'CRM_Banking_DAO_PluginInstance',
      'table' => 'civicrm_bank_plugin_instance',
  );
}


/**
 * Implements hook_civicrm_tabset()
 *
 * Will inject the "Banking Accounts" tab
 */
function banking_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName == 'civicrm/contact/view' && !empty($context['contact_id'])) {
    $contactID = (int) $context['contact_id'];
    $count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_bank_account WHERE contact_id={$contactID};");
    $tabs[] = [
        'id'     => 'bank_accounts',
        'url'    => CRM_Utils_System::url('civicrm/banking/accounts_tab', "snippet=1&amp;cid=$contactID"),
        'title'  => E::ts("Bank Accounts"),
        'count'  => $count,
        'weight' => 95
    ];
  }
}



/* bank accounts in merge operations
 */
function banking_civicrm_merge ( $type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL ) {
  switch ($type) {
    case 'relTables':
      // Offer user to merge bank accounts
      $data['rel_table_bankaccounts'] = array(
          'title'  => E::ts('Bank Accounts'),
          'tables' => array('civicrm_bank_account'),
          'url'    => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=$cid&selectedChild=bank_accounts'),  // '$cid' will be automatically replaced
      );
    break;

    case 'cidRefs':
      // this is the only field that needs to be modified
        $data['civicrm_bank_account'] = array('contact_id');
    break;
  }
}

/**
 * alterAPIPermissions() hook allows you to change the permissions checked when doing API 3 calls.
 */
function banking_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions)
{
  $permissions['banking_account']['create'] = array('delete contacts');
  $permissions['banking_account']['delete'] = array('delete contacts');
  $permissions['banking_account_reference']['create'] = array('delete contacts');
  $permissions['banking_account_reference']['check'] = array('access CiviCRM');
  $permissions['banking_transaction']['analyselist'] = array('edit contributions');
}



/**
 * crawls the menu tree to find the (currently) biggest navID
 */
function banking_civicrm_get_max_nav_id($menu) {
  $max_id = 1;
  foreach ($menu as $entry) {
    if (isset($entry['attributes']['navID'])) {
      $max_id = max($max_id, $entry['attributes']['navID']);
      if (!empty($entry['child'])) {
        $max_id_children = banking_civicrm_get_max_nav_id($entry['child']);
        $max_id = max($max_id, $max_id_children);
      }
    }
  }
  return $max_id;
}


/**
 * Replace (some of) the summary blocks on the banking review page
 *
 * @param CRM_Banking_BAO_BankTransaction $banking_transaction
 * @param array $summary_blocks
 */
function banking_civicrm_banking_transaction_summary($banking_transaction, &$summary_blocks)
{
  // Add rule match indicators:
  $ruleMatchIndicators = new CRM_Banking_RuleMatchIndicators($banking_transaction, $summary_blocks);
  $ruleMatchIndicators->addContactMatchIndicator();
  $ruleMatchIndicators->addIbanMatchIndicator();
}

/**
 * Inject contribution - transaction link
 */
function banking_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contribute_Page_Tab') {
    CRM_Banking_BAO_BankTransactionContribution::injectLinkedTransactions($page);
  }
}
