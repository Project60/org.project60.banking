<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2019 SYSTOPIA                            |
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
 * This is a XML parser implementation based on the regular XML importer.
 *
 * The difference here is, that it can deal with multiple statements in one session
 *
 * @see https://github.com/Project60/org.project60.banking/issues/261
 */
class CRM_Banking_PluginImpl_Importer_XMLMulti extends CRM_Banking_PluginImpl_Importer_XML {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->stmt_path)) $config->stmt_path = 'BkToCstmrStmt/Stmt';
  }

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  static function displayName()
  {
    return 'XML Multi-Importer';
  }

  /**
   * Imports the given XML file
   *  !overrides parent function!
   */
  function import_file( $file_path, $params ) {
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));
    $this->initDocument($file_path, $params);

    $statements = $this->xpath->query($config->stmt_path);
    foreach ($statements as $statement) {
      $this->importStatement($statement);
    }

    $this->reportDone();
  }
}

