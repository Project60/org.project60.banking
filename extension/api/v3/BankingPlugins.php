<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 P. Delbar                      |
| Author: P. Delbar                                      |
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
 * File for the CiviCRM APIv3 banking_payment functions
 *
 * @package CiviBanking
 *
 */


/**
 * Get all plugin classes
 *
 * Allowed @params array keys are:
 *  'type': either 'import', 'export', 'matcher' or 'all' (default)
 *
 * @example BankingPlugins.php Standard Create Example
 *
 * @return array API result array
 * {@getfields banking_transaction_create}
 * @access public
 */
function civicrm_api3_banking_plugins_list($params) {
  //TODO: read from database
  $all_classes = array(
      'CRM_Banking_PluginImpl_Dummy',
  );

  // filter them
  $filter = 'CRM_Banking_PluginModel_Base';
  if (isset($params['type'])) {
    if ($params['type']=='import') {
      $filter = 'CRM_Banking_PluginModel_Importer';
    } elseif ($params['type']=='export') {
      $filter = 'CRM_Banking_PluginModel_Exporter';
    } elseif ($params['type']=='matcher') {
      $filter = 'CRM_Banking_PluginModel_Matcher';
    }
  }

  $entries = array();
  foreach ($all_classes as $entry) {
    if (is_subclass_of($entry, $filter)) {
      array_push($entries, array( 
                                  'id' => 1,    // TODO: change! 
                                  'class' => $entry,
                                  'name' => $entry::displayName(),
                                  'files' => $entry::does_import_files(),
                                  'stream' => $entry::does_import_stream(),
                                )
      );
    }
  }

  return array('values'   => $entries,
               'is_error' => 0);
}



