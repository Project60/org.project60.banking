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
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  abstract function export_file( $btx_list, $file_path, $parameters );

  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  abstract function export_stream( $btx_list, $parameters );

}

