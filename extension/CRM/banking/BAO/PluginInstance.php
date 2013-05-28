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
    $classNameId = $this->plugin_class_id;
    $className = CRM_Core_OptionGroup::getValue('civicrm_banking.plugin_classes', $classNameId);
    if (trim($className) == '') {
      CRM_Core_Error::fatal(sprintf(ts('Could not locate the class name for civicrm_banking.plugin_classes member %d.'), $classNameId));
    }

    if (!class_exists($className)) {
      CRM_Core_Error::fatal(sprintf(ts('This plugin requires class %s which does not seem to exist.'), $className));
    }
    return new $className($this);
  }

}

