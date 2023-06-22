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

class CRM_Banking_Page_Import extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Bank Transaction Importer'));

    // get the plugins
    $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');

    // check for the page mode
    if (isset($_REQUEST['importer-plugin'])) {
      // RUN MODE
      $this->assign('page_mode', 'run');
      $plugin_id = $_REQUEST['importer-plugin'];
      $this->assign('plugin_id', $plugin_id);

      // assign values
      $this->assign('dry_run', isset($_REQUEST['dry_run'])?$_REQUEST['dry_run']:"off");
      $this->assign('process', isset($_REQUEST['process'])?$_REQUEST['process']:"off");
      foreach ($plugin_list as $plugin) {
        if ($plugin->id == $plugin_id) {
          $this->assign('plugin_list', array($plugin));
          break;
        }
      }

      // RUN the importer
      $file_info = isset($_FILES['uploadFile'])?$_FILES['uploadFile']:null;

      $this->assign('file_info', $file_info);
      $plugin_instance = $plugin->getInstance();
      $import_parameters = array( 'dry_run' => (isset($_REQUEST['dry_run'])?$_REQUEST['dry_run']:"off"),
                                  'source' => (isset($file_info['name'])?$file_info['name']:'stream'),
                                  );
      if ($file_info!=null && $plugin_instance::does_import_files()) {
        // run file import
        $file = $file_info['tmp_name'];
        if ($plugin_instance->probe_file($file, $import_parameters)) {
          $plugin_instance->import_file($file, $import_parameters);
        } else {
          CRM_Core_Session::setStatus(E::ts('File rejected by importer!'), E::ts('Bad input file'), 'alert');
        }

      } else if ($plugin_instance::does_import_stream()) {
        // run stream import
        if ($plugin_instance->probe_stream($import_parameters)) {
          $plugin_instance->import_stream($import_parameters);
        } else {
          CRM_Core_Session::setStatus(E::ts('Import stream rejected by importer, maybe not ready!'), E::ts('Bad input stream'), 'alert');
        }
      } else {
        CRM_Core_Session::setStatus(E::ts('Importer needs a file to proceed.'), E::ts('No input file'), 'alert');
      }

      // TODO: RUN the processor
      if (isset($_REQUEST['process']) && $_REQUEST['process']=="on") {
        CRM_Core_Session::setStatus(E::ts('Automated running not yet implemented'), E::ts('Not implemented'), 'alert');
      }

      // add the resulting log
      $log = $plugin_instance->getLog();
      $this->assign('log', $log);

      // skim through the log and make error messages pop up, see BANKING-136
      foreach ($log as $log_entry) {
        if ($log_entry[3]==CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN) {
          CRM_Core_Session::setStatus($log_entry[2], E::ts('Import Warning'), 'warn');
        } elseif ($log_entry[3]==CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN) {
          CRM_Core_Session::setStatus($log_entry[2], E::ts('Import Error'), 'error');
        }
      }
    } else {
      // CONFIGURATION MODE:
      $this->assign('page_mode', 'config');
      $this->assign('plugin_list', $plugin_list);

      // extract the sources for the plugins
      $has_file_source = array();
      foreach ($plugin_list as $plugin) {
        $class = $plugin->getClass();
        if ($class::does_import_files()) {
          $has_file_source[$plugin->id] = 'true';
        } else {
          $has_file_source[$plugin->id] = 'false';
        }

      }
      $this->assign('has_file_source', $has_file_source);
    }

    // URLs
    $new_ui_enabled = CRM_Core_BAO_Setting::getItem('CiviBanking', 'new_ui');
    if ($new_ui_enabled) {
      $this->assign('url_payments', CRM_Utils_System::url('civicrm/banking/statements'));
    } else {
      $this->assign('url_payments', CRM_Utils_System::url(
        'civicrm/banking/payments',
        'show=statements&recent=' . empty($_REQUEST['recent']) ? 0 : 1));
    }
    $this->assign('url_action', CRM_Utils_System::url('civicrm/banking/import'));

    parent::run();
  }
}
