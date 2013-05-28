<?php
// $Id$
/*
 +--------------------------------------------------------------------+
 | Project60 version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright ???????? (c) 2004-2013                                   |
 +--------------------------------------------------------------------+
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

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

  $filtered_classes = array();
  foreach ($all_classes as $entry) {
    if (is_subclass_of($entry, $filter)) {
      array_push($filtered_classes, $entry);
    }
  }

  return array('values'   => $filtered_classes,
               'is_error' => 0);
}



