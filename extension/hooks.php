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
                  'separator' => 0,
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
                  'label' => 'Accounts',
                  'name' => 'Accounts',
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
                  'label' => 'Manage Components',
                  'name' => 'Manage Components',
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
