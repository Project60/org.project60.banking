<?php

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
                  'label' => 'Dashboard',
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
                  'label' => 'Payments',
                  'name' => 'Payments',
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
                  'label' => 'Find Accounts',
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
                  'label' => 'Manage Accounts',
                  'name' => 'Manage Accounts',
                  'url' => 'civicrm/banking/accounts',
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
                  'label' => 'Import Payments',
                  'name' => 'Import Payments',
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
                  'label' => 'Configuration',
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
      // Offer user to merge SEPA Mandates
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