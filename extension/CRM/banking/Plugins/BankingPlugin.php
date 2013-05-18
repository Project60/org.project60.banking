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
  protected $_plugin_config;

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct();

    // load config
    // TODO: replace dummy:
    $this->$_plugin_id = 'dummy';
    $this->$_plugin_config = array('dummy' => 'dummy');
  }

  // ------------------------------------------------------
  // utility functions provided to the plugin implementations
  // ------------------------------------------------------
  /** 
   * Report progress of the import/export/matching process
   * 
   * TODO: data format? float [0..1]?   
   */
  function reportProgress($progress)
  {
    // TODO: implement
    print_r($progress);
  }

  /** 
   * Report progress of the import/export/matching process
   * 
   * TODO: data format? float [0..1]?   
   */
  function reportDone($error=None)
  {
    // TODO: implement
    print_r("Done!");
    print_r($error);
  }

}

