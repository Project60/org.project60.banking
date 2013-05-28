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
class CRM_Banking_PluginImpl_Dummy extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);
  }

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'Dummy Importer';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  static function does_import_files()
  {
    return FALSE;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_import_stream()
  {
    return TRUE;
  }

  /** 
   * Test if the given file can be imported
   * 
   * @var 
   * @return TODO: data format? 
   */
  function probe_file( $file_path )
  {
    return FALSE;
  }


  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_file( $file_path )
  {
    $this->reportDone(array());
    return FALSE;
  }

  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  function probe_stream()
  {
    return TRUE;
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_stream()
  {
    $this->reportProgress(0.0);
    // TODO: import dummy data
    print_r("Olé!");

    $this->reportProgress(1.0);
  }


}

