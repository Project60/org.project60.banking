<?php

require_once 'banking.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function banking_civicrm_config(&$config) {
  _banking_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function banking_civicrm_xmlMenu(&$files) {
  _banking_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function banking_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sql = file_get_contents(dirname( __FILE__ ) .'/sql/banking.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);
  
  //add the required option groups
  $params = array(
      'name' => 'civicrm_banking.plugin_types',
      'version' => 3,
  );
  $result = civicrm_api('option_group', 'get', $params);
  if ($params['is_error']) {
    $params = array(
        'version' => 3,
        'sequential' => 1,
        'name' => 'civicrm_banking.plugin_types',
        'is_reserved' => 1,
        'is_active' => 1,
        'api.OptionValue.create' => array(
            array(
                'label' => 'import',
                'value' => 1,
                'is_default' => 0,
                'is_active' => 1,
            ),
            array(
                'label' => 'match',
                'value' => 2,
                'is_default' => 0,
                'is_active' => 1,
            ),
            array(
                'label' => 'export',
                'value' => 3,
                'is_default' => 0,
                'is_active' => 1,
            ),
        ),
    );
    $result = civicrm_api('option_group', 'create', $params);    
  }
  
    $params = array(
      'name' => 'civicrm_banking.plugin_classes',
      'version' => 3,
  );
  $result = civicrm_api('option_group', 'get', $params);
  if ($params['is_error']) {
    $params = array(
        'version' => 3,
        'sequential' => 1,
        'name' => 'civicrm_banking.plugin_classes',
        'is_reserved' => 1,
        'is_active' => 1,
    );
    $result = civicrm_api('option_group', 'create', $params);    
  }

  return _banking_civix_civicrm_install();
}


/**
 * Implementation of hook_civicrm_uninstall
 */
function banking_civicrm_uninstall() {
  return _banking_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function banking_civicrm_enable() {
  return _banking_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function banking_civicrm_disable() {
  return _banking_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function banking_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _banking_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function banking_civicrm_managed(&$entities) {
  return _banking_civix_civicrm_managed($entities);
}

/**
 * HACK: Implementation of banking_civicrm_navigationMenu
 *
 * Insert Banking menu at top level
 */
function banking_civicrm_navigationMenu( &$params ) {
 
    //  Get the maximum key of $params
    $maxKey = ( max( array_keys($params) ) );
    $insert_at = min(4, max(array_keys($params)));
 
    $banking_entry = array (
                       'attributes' => array (
                                              'label'      => 'Banking',
                                              'name'       => 'CiviBanking',
                                              'url'        => null,
                                              'permission' => null,
                                              'operator'   => null,
                                              'separator'  => null,
                                              'parentID'   => null,
                                              'navID'      => $insert_at,
                                              'active'     => 1
                                              ),
                       'child' =>  array (
                                          '1' => array (
                                                        'attributes' => array (
                                                                               'label'      => 'Payments',
                                                                               'name'       => 'Payments',
                                                                               'url'        => 'civicrm/banking/payments',
                                                                               'permission' => 'access CiviContribute',
                                                                               'operator'   => null,
                                                                               'separator'  => 0,
                                                                               'parentID'   => $insert_at,
                                                                               'navID'      => 1,
                                                                               'active'     => 1
                                                                                ),
                                                        'child' => null
                                                        ),
                                          '2' => array (
                                                        'attributes' => array (
                                                                               'label'      => 'Import Payments',
                                                                               'name'       => 'Import Payments',
                                                                               'url'        => 'civicrm/banking/import',
                                                                               'permission' => 'access CiviContribute',
                                                                               'operator'   => null,
                                                                               'separator'  => 1,
                                                                               'parentID'   => $insert_at,
                                                                               'navID'      => 2,
                                                                               'active'     => 1
                                                                                ),
                                                        'child' => null
                                                        ),
                                          '3' => array (
                                                        'attributes' => array (
                                                                               'label'      => 'Accounts',
                                                                               'name'       => 'Accounts',
                                                                               'url'        => 'civicrm/banking/accounts',
                                                                               'permission' => 'access CiviContribute',
                                                                               'operator'   => null,
                                                                               'separator'  => 1,
                                                                               'parentID'   => $insert_at,
                                                                               'navID'      => 3,
                                                                               'active'     => 1
                                                                                ),
                                                        'child' => null
                                                        ),
                                          '4' => array (
                                                        'attributes' => array (
                                                                               'label'      => 'Manage Components',
                                                                               'name'       => 'Manage Components',
                                                                               'url'        => 'civicrm/banking/manager',
                                                                               'permission' => 'access CiviContribute',
                                                                               'operator'   => null,
                                                                               'separator'  => 0,
                                                                               'parentID'   => $insert_at,
                                                                               'navID'      => 4,
                                                                               'active'     => 1
                                                                                ),
                                                        'child' => null
                                                        )
										)
							);

	$params = array_merge(array_slice($params, 0, $insert_at), array($banking_entry), array_slice($params, $insert_at));
}