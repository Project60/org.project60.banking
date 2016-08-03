<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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


/**
 * CiviBanking hooks
 */

/**
 * Implementation of banking_civicrm_navigationMenu
 *
 * Insert Banking menu at top level OR submenu of "Contribtion"
 */
function banking_civicrm_navigationMenu(&$params) {
  $menu_position = (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'menu_position');
  if ($menu_position == 2) return; // menu is off, see CRM_Admin_Form_Setting_BankingSettings

  // First: have a look at the menu
  $index = 0;
  $banking_entry_index = -1;
  $contributions_entry = NULL;
  $contributions_entry_index = -1;
  foreach ($params as $key => $top_level_entry) {
    if ($top_level_entry['attributes']['name'] == 'CiviBanking') {
      $banking_entry_index = $index;
    } elseif ($top_level_entry['attributes']['name'] == 'Contributions') {
      $contributions_entry_index = $index;
      $contributions_entry = $top_level_entry;
    }
    $index++;
  }

  if ($banking_entry_index >= 0) {
    // there already is a CiviBanking top level menu => do nothing
    return;
  } elseif ($contributions_entry_index >= 0) {
    // splice it in right after the contributions menu
    $insert_at = $contributions_entry_index + 1;
  } else {
    // no contributions menu => just put it in somewhere...
    $insert_at = min(4, max(array_keys($params)));  
  }

  // NOW: Create a new top level menu
  $max_key_in_menu = banking_civicrm_get_max_nav_id($params);
  $max_key_in_db   = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
  $nav_id          = max($max_key_in_db, $max_key_in_menu) + 1;

  $banking_entry = array(
      'attributes' => array(
          'label' => 'Banking',
          'name' => 'CiviBanking',
          'url' => null,
          'permission' => null,
          'operator' => null,
          'separator' => 0,
          'parentID' => null,
          'navID' => $nav_id,
          'active' => 1
      ),
      'child' => array(
          ($nav_id + 1) => array(
              'attributes' => array(
                  'label' => ts('Dashboard'),
                  'name' => 'Dashboard',
                  'url' => 'civicrm/banking/dashboard',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $nav_id,
                  'navID' => $nav_id + 1,
                  'active' => 1
              ),
              'child' => null
          ),
          ($nav_id + 2) => array(
              'attributes' => array(
                  'label' => ts('Show Transactions'),
                  'name' => 'Transactions',
                  'url' => 'civicrm/banking/payments',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 1,
                  'parentID' => $nav_id,
                  'navID' => $nav_id + 2,
                  'active' => 1
              ),
              'child' => null
          ),
          ($nav_id + 3) => array(
              'attributes' => array(
                  'label' => ts('Find Accounts'),
                  'name' => 'Find Accounts',
                  'url' => 'civicrm/banking/search',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $nav_id,
                  'navID' => $nav_id + 3,
                  'active' => 1
              ),
              'child' => null
          ),
          ($nav_id + 4) => array(
              'attributes' => array(
                  'label' => ts('Dedupe Accounts'),
                  'name' => 'Dedupe Accounts',
                  'url' => 'civicrm/banking/dedupe',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 1,
                  'parentID' => $nav_id,
                  'navID' => $nav_id + 4,
                  'active' => 1
              ),
              'child' => null
          ),
          ($nav_id + 5) => array(
              'attributes' => array(
                  'label' => ts('Import Transactions'),
                  'name' => 'Import Transactions',
                  'url' => 'civicrm/banking/import',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $nav_id,
                  'navID' => $nav_id + 5,
                  'active' => 1
              ),
              'child' => null
          ),
      )
  );
  
  // ...and insert at the previously determined position
  if ($menu_position == 0) {
    // in this case: top level, right after "Contribution"
    $params = array_merge(array_slice($params, 0, $insert_at), array($banking_entry), array_slice($params, $insert_at));
  } elseif ($menu_position == 1) {
    // otherwise: as a submenu of "Contribution"
    $contributions_entry_id = $contributions_entry['attributes']['navID'];
    $banking_entry['attributes']['parentID'] = $contributions_entry_id;
    $banking_entry['attributes']['separator'] = 2;
    $params[$contributions_entry_id]['child'][] = $banking_entry;
  } else {
    // undefined menu position... ignore
    error_log("org.project60.banking: invalid menu_position $menu_position");
  }
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


function banking_civicrm_tabs( &$tabs, $contactID ) {
  $count_query = CRM_Core_DAO::executeQuery("SELECT COUNT(id) AS acCount FROM civicrm_bank_account WHERE contact_id=$contactID;");
  $count_query->fetch();
  array_push($tabs, array(
    'id' =>       'bank_accounts',
    'url' =>      CRM_Utils_System::url('civicrm/banking/accounts_tab', "snippet=1&amp;cid=$contactID"),
    'title' =>    ts("Bank Accounts"),
    'weight' =>   95,
    'count' =>    $count_query->acCount));
}


/* bank accounts in merge operations
 */
function banking_civicrm_merge ( $type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL ) {
  switch ($type) {
    case 'relTables':
      // Offer user to merge bank accounts
      $data['rel_table_bankaccounts'] = array(
          'title'  => ts('Bank Accounts'),
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
    $max_id = max($max_id, $entry['attributes']['navID']);
    if (!empty($entry['child'])) {
      $max_id_children = banking_civicrm_get_max_nav_id($entry['child']);
      $max_id = max($max_id, $max_id_children);
    }
  }
  return $max_id;
}
