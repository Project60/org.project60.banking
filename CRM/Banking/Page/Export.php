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

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Export extends CRM_Core_Page {
  function run() {
    CRM_Utils_System::setTitle(E::ts('Bank Transaction Exporter'));

    // get the plugins
    $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('export');
    $this->assign('plugin_list', $plugin_list);

    // get the IDs
    $txbatch2ids = CRM_Banking_PluginModel_Exporter::getIdLists($_REQUEST);
    $txcount = 0;
    foreach ($txbatch2ids as $txbatchid => $txbatchcontent) {
      $txcount += count($txbatchcontent);
    }
    $this->assign('txbatch_count', count($txbatch2ids));
    $this->assign('tx_count', $txcount);

    if (!empty($_REQUEST['list']))   $this->assign('list',   $_REQUEST['list']);
    if (!empty($_REQUEST['s_list'])) $this->assign('s_list', $_REQUEST['s_list']);

    // check for the page mode
    if (isset($_REQUEST['exporter-plugin'])) {
      // EXECUTE

      // get the plugin instance
      $plugin_id = $_REQUEST['exporter-plugin'];
      foreach ($plugin_list as $plugin) {
        if ($plugin->id == $plugin_id) {
          break;
        } 
      }
      $plugin_instance = $plugin->getInstance();

      // TODO: select WHICH mode (this is only file mode)
      
      // start exporting
      $file_data = $plugin_instance->export_file($txbatch2ids, $_REQUEST);

      // process result (redirect, ...)
      if (empty($file_data['is_error'])) {
        $buffer = file_get_contents($file_data['path']);
        CRM_Utils_System::download($file_data['file_name'], $file_data['mime_type'], $buffer, $file_data['file_extension']);
      }

    } else {
      // CONFIGURATION MODE:
      $plugin_capabilities = array();
      foreach ($plugin_list as $plugin) {
        $capability = '';
        $instance = $plugin->getInstance();
        if ($instance->does_export_files())  $capability .= 'F';
        if ($instance->does_export_stream()) $capability .= 'S';
        $plugin_capabilities[$plugin->id] = $capability;
      }
      $this->assign('plugin_capabilities', $plugin_capabilities);
    }

    // URLs
    $this->assign('url_action', CRM_Utils_System::url('civicrm/banking/export'));

    parent::run();
  }
}
