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

require_once 'banking.civix.php';
require_once 'hooks.php';
require_once 'banking_options.php';

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
  $sql = file_get_contents(dirname(__FILE__) . '/sql/banking.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

  //add the required option groups
  banking_civicrm_install_options(_banking_options());

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
  //add the required option groups
  banking_civicrm_install_options(_banking_options());

  // run the update script
  $config = CRM_Core_Config::singleton();
  $sql = file_get_contents(dirname(__FILE__) . '/sql/upgrade.sql', true);
  CRM_Utils_File::sourceSQLFile($config->dsn, $sql, NULL, true);

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

