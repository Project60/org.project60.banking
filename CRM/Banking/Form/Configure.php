<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Banking_Form_Configure extends CRM_Core_Form {

  protected $plugin = NULL;

  public function buildQuickForm() {

    // load Plugin
    $plugin_id = CRM_Utils_Request::retrieve('pid', 'Integer');
    $type_map = $this->getPluginTypeMap();
    if (empty($plugin_id)) {
      $this->plugin = array(
        'name'            => E::ts("Enter name"),
        'description'     => E::ts("Describe here what this plugin does."),
        'config'          => '{}',
        'plugin_type_id'  => CRM_Utils_Request::retrieve('type', 'Integer'),
        'plugin_class_id' => '');
    } else {
      $this->plugin = civicrm_api3('BankingPluginInstance', 'getsingle', array('id' => $plugin_id));
    }

    // set default editor mode
    $json_editor_mode = CRM_Core_BAO_Setting::getItem('CiviBanking', 'json_editor_mode');
    if (empty($json_editor_mode)) {
      $json_editor_mode = 'text';
    }
    $this->assign('json_editor_mode', $json_editor_mode);

    // set title
    CRM_Utils_System::setTitle(E::ts('Configure Plugin "%1"', array(1 => $this->plugin['name'])));

    // add form elements
    $this->addElement('text',
                      'name',
                      E::ts('Plugin Name'),
                      array('class' => 'huge'),
                      TRUE);

    $this->addElement('select',
                      'plugin_type_id',
                      E::ts('Plugin Class'),
                      $this->getOptionValueList('civicrm_banking.plugin_classes'), // yes, it's swapped
                      array('class' => 'crm-select2 huge'));

    $this->assign('type_map', json_encode($type_map));
    $this->addElement('select',
                      'plugin_class_id',
                      E::ts('Implementation'),
                      CRM_Utils_Array::value($this->plugin['plugin_type_id'], $type_map),
                      array('class' => 'crm-select2 huge'));

    $this->addElement('textarea',
                      'description',
                      E::ts('Description'),
                      array('class' => 'huge'),
                      TRUE);

    $this->add('hidden', 'configuration', base64_encode(htmlentities($this->plugin['config'])));
    $this->add('hidden', 'pid', $plugin_id);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
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
        'description'     => isset($this->plugin['description']) ? $this->plugin['description'] : '',
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
    $plugin_instance = civicrm_api3('BankingPluginInstance', 'create', $update);

    // set the config via SQL (API causes issues)
    if (empty($plugin_instance['id'])) {
      throw new Exception("Couldn't store configuration");
    } else {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_bank_plugin_instance SET config=%1 WHERE id=%2;", array(
        1 => array($values['configuration'], 'String'),
        2 => array($plugin_instance['id'],   'Integer')));
    }

    // parent::postProcess();
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
