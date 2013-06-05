<?php

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Import extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Bank Payment Importer'));

    // get the plugins
    $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');

    // check for the page mode
    if (isset($_POST['importer-plugin'])) {
      // RUN MODE
      $this->assign('page_mode', 'run');
      $plugin_id = $_POST['importer-plugin'];

      // assign values
      $this->assign('dry_run', isset($_POST['dry_run'])?$_POST['dry_run']:"off");
      $this->assign('process', isset($_POST['process'])?$_POST['process']:"off");
      foreach ($plugin_list as $plugin) {
        if ($plugin->id == $plugin_id) {
          $this->assign('plugin_list', array($plugin));
          break;
        } 
      }

      // RUN the importer
      $plugin_instance = $plugin->getInstance();
      $plugin_instance->import_stream(array('dry_run' => (isset($_POST['dry_run'])?$_POST['dry_run']:"off")));
      
      // TODO: RUN the processor
      if (isset($_POST['process']) && $_POST['process']=="on") {
        CRM_Core_Session::setStatus(ts('Automated running not yet implemented'), ts('Not implemented'), 'alert');
      }

      // add the resulting log
      $this->assign('log', $plugin_instance->getLog());
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
    $this->assign('url_action', CRM_Utils_System::url('civicrm/banking/import'));
    $this->assign('url_payments', CRM_Utils_System::url('civicrm/banking/payments', 'show=payments'));

    parent::run();
  }
}
