<?php

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
    $import_plugin_type = civicrm_api('OptionValue', 'get', array('version' => 3, 'name' => $type_name, 'option_group_id' => $plugin_types['id']));
    if (isset($result['is_error']) && $result['is_error']) {
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
      for ($index=0; $index < count($plugin_list); $index++) {
        if ($plugin->weight > $plugin_list[$index]->weight) {
          array_splice($plugin_list, $index, 0, [$plugin]);
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

