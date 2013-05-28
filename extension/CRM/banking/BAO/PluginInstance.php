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
   * getInstance returns an instance of the class implementing this plugin's functionality
   */
  function getInstance() {
    $className = $this->plugin_class_id;
    $classGroup = civicrm_api( 'option_group','get', array( 'version' => 3, 'name' => 'civicrm_banking.plugin_classes' ) );
    if ($classGroup)
    if (!class_exists($className)) {
      CRM_Core_Error::fatal(sprintf( ts('This plugin requires class %s which does not seem to exist.'), $cl));
    }
    return new $className($this);
  }

}

