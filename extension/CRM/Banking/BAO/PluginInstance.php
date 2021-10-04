<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
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
 * Class contains functions for CiviBanking plugin instances
 */
class CRM_Banking_BAO_PluginInstance extends CRM_Banking_DAO_PluginInstance {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankAccount object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'PluginInstance', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_PluginInstance();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'PluginInstance', $dao->id, $dao);
    return $dao;
  }

  /**
   * get a list of all plugin instances of the given type ('import', 'export', 'matcher').
   *
   * If $enabled_only is set to true (default), only enabled plugins will be delivered.
   *
   * @return array CRM_Banking_BAO_PluginInstances
   */
  static function listInstances($type_name, $enabled_only=TRUE) {
    // first, find the plugin type option group
    $plugin_types = civicrm_api3('OptionGroup', 'get', array(
        'name' => 'civicrm_banking.plugin_types'));

    // then, find the correct plugin type
    $import_plugin_type = civicrm_api3('OptionValue', 'get', array(
        'name'     => $type_name,
        'group_id' => $plugin_types['id'],
        'option.limit' => 0));

    // then, get the list of plugins matching this criteria
    $params = array('plugin_type_id' => $import_plugin_type['id']);
    if ($enabled_only) { $params['enabled'] = 1; }
    $instance_results = civicrm_api3('BankingPluginInstance', 'get', $params);

    // create list of plugin instance BAOs
    $plugin_list = array();
    foreach ($instance_results['values'] as $plugin_info) {
      $plugin = new CRM_Banking_BAO_PluginInstance();
      $plugin->get('id', $plugin_info['id']);

      // insert with ascending weight
      // PD: I think this code is really complex - are you trying to sort on weight ? we can do that with a usort call
      //  BE: go ahead and replace it! But if you just comment it out, it stops working, and I get warnings (Undefined variable, below)
      for ($index=0; $index < count($plugin_list); $index++) {
        if ($plugin->weight > $plugin_list[$index]->weight) {
          array_splice($plugin_list, $index, 0, array($plugin));
          $index = count($plugin_list)-1; // for the after-loop condition
          break;
        }
      }
      if ($index==count($plugin_list)) {
        // i.e. it was not added during the loop
        array_push($plugin_list, $plugin);
      }
    }

    return $plugin_list;
  }

  /**
   * getInstance returns the class that implements this plugin's functionality
   */
  function getClass() {
    $classNameId = $this->plugin_class_id;
    $className = civicrm_api3( 'OptionValue','getsingle', array('id' => $classNameId));

    $class = $className['value'];
    if (!class_exists($class)) {
      throw new Exception(sprintf('This plugin requires class %s which does not seem to exist.', $class));
    }
    return $class;
  }

  /**
   * getInstance returns an instance of the class implementing this plugin's functionality
   */
  function getInstance() {
    $class = $this->getClass();
    return new $class( $this );
  }

  /**
   * Serialise this entire plugin for export
   */
  public function serialise() {
    $data = array();

    // load class id
    $data['plugin_type_name']  = civicrm_api3('OptionValue', 'getvalue', ['return' => 'name', 'id' => $this->plugin_type_id]);
    $data['plugin_class_name'] = civicrm_api3('OptionValue', 'getvalue', ['return' => 'name', 'id' => $this->plugin_class_id]);
    $data['name']              = $this->name;
    $data['description']       = $this->description;
    $data['weight']            = $this->weight;
    $data['config']            = json_decode($this->config);
    $data['state']             = json_decode($this->state);

    return json_encode($data, JSON_PRETTY_PRINT);
  }

  /**
   * Serialise this entire plugin for export
   */
  public function updateWithSerialisedData($serialised_data, $skip_fields = [], $verify_fields = []) {
    try {
      $data = json_decode($serialised_data, true);

      // note: sadly, type and class are inversely used!
      $plugin_type_id = civicrm_api3('OptionValue', 'getvalue', [
              'return'          => 'id',
              'option_group_id' => 'civicrm_banking.plugin_classes',
              'name'            => $data['plugin_type_name']]);
      if (in_array('plugin_type_name', $verify_fields)) {
        if ($this->plugin_type_id != $plugin_type_id) {
          throw new Exception(E::ts("Cannot update, wrong plugin type!"));
        }
      }
      $this->plugin_type_id = $plugin_type_id;

      $plugin_class_id = civicrm_api3('OptionValue', 'getvalue', [
              'return'          => 'id',
              'option_group_id' => 'civicrm_banking.plugin_types',
              'name'            => $data['plugin_class_name']]
      );
      if (in_array('plugin_class_name', $verify_fields)) {
        if ($this->plugin_class_id != $plugin_class_id) {
          throw new Exception(E::ts("Cannot update, wrong plugin class!"));
        }
      }
      $this->plugin_class_id = $plugin_class_id;

      $fields = ['name', 'description', 'weight', 'config', 'state'];
      foreach ($fields as $field) {
        $value = $data[$field];
        if ($field == 'config' || $field == 'state') {
          $value = json_encode($value);
        }

        // if requested, make sure it's the same
        if (in_array($field, $verify_fields)) {
          if ($value != $this->$field) {
            throw new Exception(E::ts("Cannot update, field %1 differs.", [1 => $field]));
          }
        }

        if (!in_array($field, $skip_fields)) {
          $this->$field = $value;
        }
      }

      $this->save();
    } catch (Exception $ex) {
      throw new Exception("Import failed: " . $ex->getMessage());
    }
  }
}

