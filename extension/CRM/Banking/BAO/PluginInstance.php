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
   * @return an array of CRM_Banking_BAO_PluginInstances
   */
  static function listInstances($type_name, $enabled_only=TRUE) {
    // first, find the plugin type option group
    $plugin_types = civicrm_api('OptionGroup', 'get', array('version' => 3, 'name' => 'civicrm_banking.plugin_types'));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::fatal(sprintf(ts("Couldn't find group '%s'!"), 'civicrm_banking.plugin_types'));
      return array();
    }

    // then, find the correct plugin type
    $import_plugin_type = civicrm_api('OptionValue', 'get', array('version' => 3, 'name' => $type_name, 'group_id' => $plugin_types['id']));
    if ((isset($result['is_error']) && $result['is_error']) || (!isset($import_plugin_type['id']) || !$import_plugin_type['id'])) {
      CRM_Core_Error::fatal(sprintf(ts("Couldn't find type '%s' in group %d!"), $type_name, $plugin_types['id']));
      return array();
    }

    // then, get the list of plugins matching this criteria
    $params = array('version' => 3, 'plugin_type_id' => $import_plugin_type['id']);
    if ($enabled_only) { $params['enabled'] = 1; }
    $instance_results = civicrm_api('BankingPluginInstance', 'get', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::fatal(ts("Couldn't query plugin list from API!"));
      return array();
    }

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
    $className = civicrm_api( 'OptionValue','getsingle', array( 
        'version' => 3, 
        'id' => $classNameId) );
    if (isset($className['is_error']) && $className['is_error']) {
      CRM_Core_Error::fatal( sprintf( ts('Could not locate the class name for civicrm_banking.plugin_classes member %d.'), $classNameId ) );
    }

    $class = $className['value'];
    if (!class_exists($class)) {
      CRM_Core_Error::fatal(sprintf( ts('This plugin requires class %s which does not seem to exist.'), $class));
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

}

