<?php

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Import extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Bank Payment Importer'));

    // find plugins
    $result = civicrm_api('BankingPlugins', 'list', array('version' => 3, 'type' => 'import'));
    $plugin_list = array();
    if ($result['is_error']) {
      CRM_Core_Error::fatal(ts("Couldn't query plugin list from API!"));
    } else {
      $plugin_list = $result['values'];
    }

    // check for the page mode
    if (isset($_POST['importer-plugin'])) {
      // RUN MODE
      $this->assign('page_mode', 'run');
      $plugin_id = $_POST['importer-plugin'];

      // assign values
      $this->assign('dry_run', isset($_POST['dry_run'])?$_POST['dry_run']:"off");
      $this->assign('process', isset($_POST['process'])?$_POST['process']:"off");
      foreach ($plugin_list as $plugin) {
        if ($plugin['id'] == $plugin_id) {
          $this->assign('plugin_list', array($plugin));
          break;
        } 
      }

      // RUN the importer
      $bao = new CRM_Banking_BAO_PluginInstance();
      $bao->get('id', $plugin_id);
      $plugin_instance = $bao->getInstance();
      $plugin_instance->import_stream(array('dry_run' => (isset($_POST['dry_run'])?$_POST['dry_run']:"off")));
      
      // TODO: RUN the importer
      if (isset($_POST['process']) && $_POST['process']=="on") {
        CRM_Core_Session::setStatus(ts('Automated running not yet implemented'), ts('Not implemented'), 'alert');
      }

      // add the resulting log
      $this->assign('log', $plugin_instance->getLog());
    } else {
      // CONFIGURATION MODE:
      $this->assign('page_mode', 'config');
      $this->assign('plugin_list', $plugin_list);

    }

    parent::run();
  }
}
