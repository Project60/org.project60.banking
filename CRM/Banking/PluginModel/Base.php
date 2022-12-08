<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

use CRM_Banking_ExtensionUtil as E;

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_Base {

  CONST REPORT_LEVEL_DEBUG = "DEBUG";
  CONST REPORT_LEVEL_INFO  = "INFO";
  CONST REPORT_LEVEL_WARN  = "WARN";
  CONST REPORT_LEVEL_ERROR = "ERROR";

  CONST REPORT_PROGRESS_NONE = -1.0;

  /**
   * The task that the wizard is currently processing
   *
   * @var string
   * @protected
   */
  public $_plugin_id;
  protected $_plugin_dao;
  protected $_plugin_weight;
  protected $_plugin_name;
  protected $_plugin_title;
  protected $_plugin_config;
  protected $_progress_callback;
  protected $_progress_log = array();

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  static function displayName() {
    return "Unknown";
  }

  /**
   * class constructor
   */
  function __construct($plugin_dao) {
    $this->__setDAO($plugin_dao);
    $this->logger = CRM_Banking_Helpers_Logger::getLogger();
  }

  protected function __setDAO($plugin_dao) {
    $this->_plugin_dao    = $plugin_dao;
    $this->_plugin_id     = $plugin_dao->id;
    $this->_plugin_weight = $plugin_dao->weight;
    $this->_plugin_title  = $plugin_dao->description;
    $this->_plugin_name   = $plugin_dao->name;
    $this->_plugin_config = json_decode( $plugin_dao->config );
    if ($this->_plugin_config==false) {
      CRM_Core_Error::fatal("Configuration for CiviBanking plugin (id: ".$plugin_dao->id.") is not a valid JSON string.");
      $this->_plugin_config = array();
    }
  }

  /**
   * Generic logging function for any plugin
   */
  public function logMessage($message, $log_level) {
    // TODO: evaluate log_level
    if (isset($this->_plugin_config->log_level) && $this->_plugin_config->log_level != 'off') {
      $this->logger->log("[{$this->_plugin_id}]: {$message}", $this->_plugin_config->log_level);
    }
  }

  /**
   * Generic logging function for any plugin
   */
  public function logTime($process, $timer) {
    // TODO: evaluate log_level
    if (isset($this->_plugin_config->log_level) && $this->_plugin_config->log_level != 'off') {
      $this->logger->logTime("[{$this->_plugin_id}]: {$process}", $timer);
    }
  }


  // ------------------------------------------------------
  // utility functions provided to the plugin implementations
  // ------------------------------------------------------
  /**
   * Set a callback for progress reports (reported by match(), import_*() and export()_*)
   *
   * TODO: data format? float [0..1]?
   */
  function setProgressCallback($callback) {
    // TODO: sanity checks?
    $this->_progress_callback = $callback;
    $this->_progress_log = array();
  }

  /**
   * Report progress of the import/export/matching process
   *
   * TODO: data format? float [0..1]?
   */
  function reportProgress($progress, $message=None, $level=self::REPORT_LEVEL_INFO) {
    if ($progress==self::REPORT_PROGRESS_NONE) {
      // this means, no new progress has been made, simple take the last one
      $last_entry = array_slice( $this->_progress_log, -1, 1, TRUE );
      if (count($last_entry)>0) {
//        $progress = reset($last_entry)[1];    // I have no clue what this does
      } else {
        $progress = 0.0;
      }
    }

    // normalize progress
    if ($progress < 0) {
      $progress = 0;
    } elseif ($progress > 1) {
      $progress = 1;
    }

    if (isset($_progress_callback)) {
      $_progress_callback->reportProgress($progress, $message, $level);
    }
    // log internally
    array_push($this->_progress_log, array(date('Y-m-d H:i:s'), $progress, $message, $level));
  }

  /**
   * Report completion import/export/matching process
   */
  function reportDone($error = 'Done') {
    $this->reportProgress(1.0, $error, ($error=='Done')?self::REPORT_LEVEL_INFO:self::REPORT_LEVEL_ERROR);
  }

  /**
   * Get the internal log of the last reports. Reset by calling setProgressCallback(None)
   */
  function getLog() {
    return $this->_progress_log;
  }

  function getTitle() {
      return $this->_plugin_title;
  }

  function getName() {
      return $this->_plugin_name;
  }

  /**
   * get the name of the implementation specification (plugin_class_id)
   */
  function getTypeName() {
    $type_id = $this->_plugin_dao->plugin_class_id;
    return civicrm_api3(
      'OptionValue',
      'getvalue',
      [
        'return' => 'name',
        'option_group_id' => 'civicrm_banking.plugin_types',
        'id' => $type_id,
      ]
    );
  }

  function getPluginID() {
    return $this->_plugin_id;
  }

  function getConfig() {
    return $this->_plugin_config;
  }

  // -------------------------------------------------------
  // search functions provided to the plugin implementations
  // -------------------------------------------------------
  /**
   * Look up contact with the given attributes
   *
   * This method is to be preferred over BAO or API calls, since results will be cached in future versions
   *
   * @return array of contacts
   */
  function findContact($attributes) {
    // TODO implement
    return array();
  }

  /**
   * Look up contributions with the given attributes
   *
   * This method is to be preferred over BAO or API calls, since results will be cached in future versions
   *
   * @return array of contacts
   */
  function findContribution($attributes) {
    // TODO implement
    return array();
  }

}
