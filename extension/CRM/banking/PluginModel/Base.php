<?php

/*
  org.project60.banking extension for CiviCRM

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_Base {

  /**
   * The task that the wizard is currently processing
   *
   * @var string
   * @protected
   */
  protected $_plugin_dao;
  protected $_plugin_id;
  protected $_plugin_weight;
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
  }

  protected function __setDAO($plugin_dao) {
    $this->_plugin_dao = $plugin_dao;
    $this->_plugin_id = $plugin_dao->id;
    $this->_plugin_weight = $plugin_dao->weight;
    $this->_plugin_config = json_decode( $plugin_dao->config );
    if ($this->_plugin_config==false) {
      CRM_Core_Error::fatal("Configuration for CiviBanking plugin (id: ".$plugin_dao->id.") is not a valid JSON string.");
      $this->_plugin_config = array();
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
  function reportProgress($progress, $message=None) {
    if (isset($_progress_callback)) {
      $_progress_callback->reportProgress($progress, $message);
    }
    // log internally
    array_push($this->_progress_log, array(date('Y-m-d H:i:s'), $progress, $message));
  }

  /**
   * Report completion import/export/matching process
   */
  function reportDone($error = 'Done') {
    if (isset($_progress_callback)) {
      $_progress_callback->reportProgress($progress, $error);
    }

    // log internally
    array_push($this->_progress_log, array(date('Y-m-d H:i:s'), 1.0, $error));
  }

  /**
   * Get the internal log of the last reports. Reset by calling setProgressCallback(None)
   */
  function getLog() {
    return $this->_progress_log;
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
