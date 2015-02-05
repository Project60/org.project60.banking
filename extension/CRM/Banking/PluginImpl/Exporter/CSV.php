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
  static function does_export_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_export_stream()
  {
    return false;
  }

  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  function export_stream( $btx_list, $parameters ) {
    $this->reportDone(ts("Importing streams not supported by this plugin."));
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function export_file( $btx_list, $file_path, $parameters ) {
    $btx_ids_to_export = $this->getIDList();
    foreach ($btx_ids_to_export as $btx_id) {
      $data = $this->getBTXData($btx_id);

      # TODO code...
      
    }
  }
}

