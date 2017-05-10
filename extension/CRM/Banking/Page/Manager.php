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

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Manager extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Manage CiviBanking Configuration'));

    // load all plugins and sort by
    $plugin_type_to_instance = array();
    $plugin_query = CRM_Core_DAO::executeQuery("
      SELECT   plugin.id            AS plugin_id,
               plugin.name          AS plugin_name,
               plugin.description   AS plugin_description,
               plugin.enabled       AS plugin_enabled,
               type.label           AS plugin_type,
               class.label          AS plugin_class,
               class.value          AS plugin_class_implementation,
               plugin.weight        AS plugin_weight
      FROM civicrm_bank_plugin_instance plugin
      LEFT JOIN civicrm_option_value type  ON type.id  = plugin_type_id
      LEFT JOIN civicrm_option_value class ON class.id = plugin_class_id
      ORDER BY plugin.weight ASC");

    while ($plugin_query->fetch()) {
      $plugin_type_to_instance[$plugin_query->plugin_type][] = array(
        'id'             => $plugin_query->plugin_id,
        'name'           => $plugin_query->plugin_name,
        'description'    => $plugin_query->plugin_description,
        'enabled'        => $plugin_query->plugin_enabled,
        'class'          => $plugin_query->plugin_class,
        'implementation' => $plugin_query->plugin_class_implementation,
        'weight'         => $plugin_query->plugin_weight);
    }

    // TODO: enrich data with information from the class,config,...?

    // assign lists to template
    $this->assign('importers',      CRM_Utils_Array::value('Import plugin', $plugin_type_to_instance, array()));
    $this->assign('matchers',       CRM_Utils_Array::value('Match plugin', $plugin_type_to_instance, array()));
    $this->assign('postprocessors', CRM_Utils_Array::value('Postprocessor plugin', $plugin_type_to_instance, array()));
    $this->assign('exporters',      CRM_Utils_Array::value('Export plugin', $plugin_type_to_instance, array()));
    $this->assign('baseurl', CRM_Utils_System::url('civicrm/banking/manager'));

    parent::run();
  }
}
