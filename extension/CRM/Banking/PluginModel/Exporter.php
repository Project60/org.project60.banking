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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_Exporter extends CRM_Banking_PluginModel_IOPlugin {

  // ------------------------------------------------------
  // Functions to be provided by the plugin implementations
  // ------------------------------------------------------
  /** 
   * Report if the plugin is capable of exporting files
   * 
   * @return bool
   */
  abstract function does_export_files();

  /** 
   * Report if the plugin is capable of exporting streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  abstract function does_export_stream();

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return URL of the resulting file
   */
  abstract function export_file( $txbatch2ids, $parameters );

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return bool TRUE if successful
   */
  abstract function export_stream( $txbatch2ids, $parameters );



  /**
   * will evaluate the 'list' (comma separated list of tx IDs) and 
   * 's_list' (comma separated list of tx_batch IDs), if given.
   *
   * @return an array('tx_batch_id' => array('tx_id'))
   */
  public static function getIdLists($params) {
    // first: extract all the IDs
    if (!empty($params['list'])) {
      $ids = explode(",", $params['list']); 
    } else {
      $ids = array();
    }
    if (!empty($params['s_list'])) {
      $list = CRM_Banking_Page_Payments::getPaymentsForStatements($params['s_list']);
      $ids = array_merge(explode(",", $list), $ids);
    }

    // now create a (sane) SQL query
    $sane_ids = array();
    foreach ($ids as $tx_id) {
      if (is_numeric($tx_id)) {
        $sane_ids[]= (int) $tx_id;
      }
    }
    if (count($sane_ids) == 0) return array();
    $sane_ids_list = implode(',', $sane_ids);

    // query the DB
    $query_sql = "SELECT id, tx_batch_id FROM civicrm_bank_tx WHERE id IN ($sane_ids_list);";
    $result = array();
    $query = CRM_Core_DAO::executeQuery($query_sql);
    while ($query->fetch()) {
      $result[$query->tx_batch_id][] = $query->id;
    }

    return $result;
  }
}

