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
class CRM_Banking_PluginImpl_Exporter_CSV extends CRM_Banking_PluginModel_Exporter {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->delimiter)) $config->delimiter = ',';
    if (!isset($config->header))    $config->header = 1;
    if (!isset($config->defaults))  $config->defaults = array();
    if (!isset($config->rules))     $config->rules = array();
  }

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'CSV Exporter';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  public function does_export_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  public function does_export_stream()
  {
    return false;
  }

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return URL of the resulting file
   */
  public function export_file( $txbatch2ids, $parameters ) {
    $file = $this->_export($txbatch2ids, $parameters);

    // TODO: turn $file into a downloadable link
    return "http://localhost:8888/mh/";
  }

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return bool TRUE if successful
   */
  public function export_stream( $txbatch2ids, $parameters ) {
    $file = $this->_export($txbatch2ids, $parameters);

    // TODO: upload $file

    return true;
  }


  /**
   * This is the main method where the actual export happens
   * 
   * It will create a temp file, export to that, and then return the local path
   */
  protected function _export($txbatch2ids, $parameters ) {
    // todo: open file
    $temp_file = '/tmp/cbexport.csv';
    // todo: create/write header from config

    foreach ($txbatch2ids as $tx_batch_id => $batch_txns) {
      // todo get information on the batch
      error_log('BATCH '.$tx_batch_id);

      foreach ($batch_txns as $tx_id) {
      error_log('  ID '.$tx_id);
        // todo: get information on the txn

        // merge: config_data, batch_data, txn_data, exporter_status and tx_suggestions
        $txdata = array();

        // then: execute rules

        // then: write row
      }
    }

    return $temp_file;    
  }

}
