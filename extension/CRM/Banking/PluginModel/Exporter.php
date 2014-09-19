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

