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
 * HACK: Implementation of banking_civicrm_navigationMenu
 *
 * Insert Banking menu at top level
 */
function banking_civicrm_navigationMenu(&$params) {

  //  Get the maximum key of $params
  $maxKey = ( max(array_keys($params)) );
  $insert_at = min(4, max(array_keys($params)));

  $level = 1;
  $banking_entry = array(
      'attributes' => array(
          'label' => 'Banking',
          'name' => 'CiviBanking',
          'url' => null,
          'permission' => null,
          'operator' => null,
          'separator' => null,
          'parentID' => null,
          'navID' => $insert_at,
          'active' => 1
      ),
      'child' => array(
          $level => array(
              'attributes' => array(
                  'label' => ts('Dashboard'),
                  'name' => 'Dashboard',
                  'url' => 'civicrm/banking/dashboard',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          ),
          $level => array(
              'attributes' => array(
                  'label' => ts('Show Transactions'),
                  'name' => 'Transactions',
                  'url' => 'civicrm/banking/payments',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 1,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          ),
          $level => array(
              'attributes' => array(
                  'label' => ts('Find Accounts'),
                  'name' => 'Find Accounts',
                  'url' => 'civicrm/banking/search',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          ),
          $level => array(
              'attributes' => array(
                  'label' => ts('Dedupe Accounts'),
                  'name' => 'Dedupe Accounts',
                  'url' => 'civicrm/banking/dedupe',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          ),
          $level => array(
              'attributes' => array(
                  'label' => ts('Import Transactions'),
                  'name' => 'Import Transactions',
                  'url' => 'civicrm/banking/import',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 1,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          ),
          $level => array(
              'attributes' => array(
                  'label' => ts('Configuration'),
                  'name' => 'Configuration',
                  'url' => 'civicrm/banking/manager',
                  'permission' => 'access CiviContribute',
                  'operator' => null,
                  'separator' => 0,
                  'parentID' => $insert_at,
                  'navID' => $level++,
                  'active' => 1
              ),
              'child' => null
          )
      )
  );

  $params = array_merge(array_slice($params, 0, $insert_at), array($banking_entry), array_slice($params, $insert_at));
}

function banking_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
  $entityTypes[] = array(
      'name' => 'BankAccount',
      'class' => 'CRM_Banking_DAO_BankAccount',
      'table' => 'civicrm_bank_account',
  );
  $entityTypes[] = array(
      'name' => 'BankTransaction',
      'class' => 'CRM_Banking_DAO_BankTransaction',
      'table' => 'civicrm_bank_tx',
  );
  $entityTypes[] = array(
      'name' => 'PluginInstance',
      'class' => 'CRM_Banking_DAO_PluginInstance',
      'table' => 'civicrm_bank_plugin_instabce',
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
  $permissions['banking_transaction']['analyselist'] = array('edit contributions');
}
