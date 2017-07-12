<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017 SYSTOPIA                            |
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
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Banking_Form_Configure extends CRM_Core_Form {

  protected $plugin = NULL;

  public function buildQuickForm() {
    $return_url = CRM_Utils_System::url('civicrm/banking/manager');

    // load Plugin
    $plugin_id = CRM_Utils_Request::retrieve('pid', 'Integer');
    if (empty($plugin_id)) {
      CRM_Core_Session::setStatus(ts("No plugin ID (pid) given"), ts("Error"), "error");
      CRM_Utils_System::redirect($return_url);
    }
    $this->plugin = civicrm_api3('BankingPluginInstance', 'getsingle', array('id' => $plugin_id));

    // set title
    CRM_Utils_System::setTitle(ts('Configure Plugin "%1"', array(1 => $this->plugin['name'])));

    // add form elements
    $this->addElement('text',
                      'name',
                      ts('Plugin Name', array('domain' => 'org.project60.banking')),
                      TRUE);

    $this->addElement('select',
                      'plugin_type_id',
                      ts('Plugin Class', array('domain' => 'org.project60.banking')),
                      $this->getOptionValueList('civicrm_banking.plugin_classes'), // yes, it's swapped
                      array('class' => 'crm-select2 huge'));

    $type_map = $this->getPluginTypeMap();
    $this->assign('type_map', json_encode($type_map));
    $this->addElement('select',
                      'plugin_class_id',
                      ts('Implementation', array('domain' => 'org.project60.banking')),
                      $type_map[$this->plugin['plugin_type_id']],
                      array('class' => 'crm-select2 huge'));

    $this->addElement('textarea',
                      'description',
                      ts('Description', array('domain' => 'org.project60.banking')),
                      TRUE);

    $this->add('hidden', 'configuration', $this->plugin['config']);
    $this->add('hidden', 'pid', $plugin_id);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
    ));


    // add JSONEditor resources
    $resources = CRM_Core_Resources::singleton();
    $resources->addScriptFile('org.project60.banking', 'packages/jsoneditor/jsoneditor.min.js');
    $resources->addStyleFile('org.project60.banking', 'packages/jsoneditor/jsoneditor.min.css');

    parent::buildQuickForm();
  }

  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    // error_log(json_encode($this->plugin['config']));
    if ($this->plugin) {
      return array(
        'name'            => $this->plugin['name'],
        'description'     => $this->plugin['description'],
        'plugin_type_id'  => $this->plugin['plugin_type_id'],
        'plugin_class_id' => $this->plugin['plugin_class_id'],
      );
    }
  }

  public function postProcess() {
    $values = $this->exportValues();

    // create/update
    $update = array(
      'plugin_class_id' => $values['plugin_class_id'],
      'plugin_type_id'  => $values['plugin_type_id'],
      'name'            => $values['name'],
      'description'     => $values['description'],
      'config'          => $values['configuration'],
      );
    if (!empty($values['pid'])) {
      // update
      $update['id'] = $values['pid'];
    } else {
      // create
      $update['enabled'] = 1;
      $update['weight']  = 1000;
      $update['state']   = '{}';
    }
    civicrm_api3('BankingPluginInstance', 'create', $update);

    parent::postProcess();
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/banking/manager'));
  }

  /**
   *
   */
  protected function getPluginTypeMap() {
    $class_search = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'civicrm_banking.plugin_classes',
      'options' => array('limit' => 0, 'sort' => "weight"),
      ));
    $class_prefix2id = array();
    foreach ($class_search['values'] as $class_id => $option_value) {
      $class_name = $option_value['name'];
      $class_prefix2id[$class_name] = $class_id;
      if ($class_name == 'match') {
        $class_prefix2id['analy'] = $class_id;
      }
    }

    $type_search = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'civicrm_banking.plugin_types',
      'options' => array('limit' => 0, 'sort' => "weight"),
      ));
    $class_id2type_id2label = array();
    foreach ($type_search['values'] as $type_id => $option_value) {
      // determine the class id
      foreach ($class_prefix2id as $prefix => $class_id) {
        $type_name   = $option_value['name'];
        $type_prefix = substr($type_name, 0, strlen($prefix));
        if ($type_prefix == $prefix) {
          $class_id2type_id2label[$class_id][$type_id] = $option_value['label'];
        }
      }
    }
    return $class_id2type_id2label;
  }

  /**
   * get an OptionValue.id => OptionValue.id list for the given group name
   */
  protected function getOptionValueList($option_group_name) {
    $list = array();
    $values = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => $option_group_name,
      'options' => array('limit' => 0,
                         'sort' => "weight"),
      ));
    foreach ($values['values'] as $option_value) {
      $list[$option_value['id']] = $option_value['label'];
    }
    return $list;
  }
}
