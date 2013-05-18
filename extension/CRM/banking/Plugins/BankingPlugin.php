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
abstract class CRM_Banking_Plugin {

  /**
   * The task that the wizard is currently processing
   *
   * @var string
   * @protected
   */
  protected $_plugin_id;
  protected $_plugin_weight;
  protected $_plugin_config;
  protected $_progress_callback;

  /**
   * class constructor
   */ function __construct($instance_id) {
    parent::__construct();
    $this->$_plugin_id = $instance_id;

    // TODO: load plugin instance entity, and read the configuration
    $this->$_plugin_config = array('dummy' => 'dummy');
    $this->$_plugin_weight = 1.0;
  }

  // ------------------------------------------------------
  // utility functions provided to the plugin implementations
  // ------------------------------------------------------
  /** 
   * Set a callback for progress reports (reported by match(), import_*() and export()_*)
   * 
   * TODO: data format? float [0..1]?   
   */
  function setProgressCallback($callback)
  {
    // TODO: sanity checks?
    $this->_progress_callback = $callback;
  }


  /** 
   * Report progress of the import/export/matching process
   * 
   * TODO: data format? float [0..1]?   
   */
  function reportProgress($progress)
  {
    if (isset($_progress_callback)) {
      $_progress_callback->reportProgress($progress);
    } else {
      // TODO: implement    
      print_r($progress);
    }
  }

  /** 
   * Report progress of the import/export/matching process
   * 
   * TODO: data format? float [0..1]?   
   */
  function reportDone($error=None)
  {
    if (isset($_progress_callback)) {
      $_progress_callback->reportProgress($progress);
    } else {
      // TODO: implement
      print_r("Done!");
      print_r($error);
    }
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
  function findContact( $attributes )
  {
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
  function findContribution( $attributes )
  {
    // TODO implement
    return array();
  }

}
