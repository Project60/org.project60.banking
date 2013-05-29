<?php

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Import extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Bank Payment Importer'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));

    // find plugins
    $params = array(
  		'version' => 3,
  		'type' => 'import',
  	);
  	$result = civicrm_api('BankingPlugins', 'list', $params);
  	if ($result['is_error']) {
  		CRM_Core_Error::fatal($r["Couldn't query plugin list from API!"]);
  		$this->assign('plugin_list', array());
  	} else {
  		$this->assign('plugin_list', $result['values']);
  	}


    // check for the page mode
    if (isset($_POST['importer-plugin'])) {
      // RUN MODE
      $this->assign('page_mode', 'run');

      // TODO: fix up test code:
      $bao = new CRM_Banking_BAO_PluginInstance();
      $bao->get('id', 1);
      $plugin_instance = $bao->getInstance();
      $plugin_instance->import_stream();
      
      // 
      $this->assign('log', $plugin_instance->getLog());



    } else {
      // CONFIGURATION MODE
      
      $this->assign('page_mode', 'config');


    }

    parent::run();
  }
}
