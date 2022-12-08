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

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Manager extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(E::ts('Manage CiviBanking Configuration'));

    // first: process commands (if any)
    $this->processDeleteCommand();
    $this->processEnableDisableCommand();
    $this->processRearrangeCommand();
    $this->processExportCommand();

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

    // set the type IDs
    $class_search = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'civicrm_banking.plugin_classes',
      'options' => array('limit' => 0, 'sort' => "weight"),
      ));
    foreach ($class_search['values'] as $optionValue) {
      $this->assign("type_{$optionValue['name']}", $optionValue['id']);
    }

    // assign lists to template
    $this->assign('importers',      CRM_Utils_Array::value('Import plugin',  $plugin_type_to_instance, array()));
    $this->assign('matchers',       CRM_Utils_Array::value('Match plugin',   $plugin_type_to_instance, array()));
    $this->assign('postprocessors', CRM_Utils_Array::value('Post Processor', $plugin_type_to_instance, array()));
    $this->assign('exporters',      CRM_Utils_Array::value('Export plugin',  $plugin_type_to_instance, array()));
    $this->assign('baseurl', CRM_Utils_System::url('civicrm/banking/manager'));

    parent::run();
  }


  /**
   * Delete Plugin
   */
  protected function processDeleteCommand() {
    $delete_id = CRM_Utils_Request::retrieve('delete', 'Integer');
    $confirmed = CRM_Utils_Request::retrieve('confirmed', 'Integer');
    if ($delete_id) {
      if ($confirmed) {
        civicrm_api3('BankingPluginInstance', 'delete', array('id' => $delete_id));
        CRM_Core_Session::setStatus(E::ts("CiviBanking plugin [%1] deleted.", array(1 => $delete_id)), E::ts("Plugin deleted"), "info");
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/banking/manager'));
      } else {
        $plugin = civicrm_api3('BankingPluginInstance', 'getsingle', array('id' => $delete_id));
        $this->assign('delete', $plugin);
      }
    }
  }

  /**
   * Process the 'enable' and 'disable' command
   */
  protected function processEnableDisableCommand() {
    $enable_id  = CRM_Utils_Request::retrieve('enable', 'Integer');
    $disable_id = CRM_Utils_Request::retrieve('disable', 'Integer');

    if ($enable_id) {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_bank_plugin_instance SET enabled = 1 WHERE id = {$enable_id};");
    }

    if ($disable_id) {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_bank_plugin_instance SET enabled = 0 WHERE id = {$disable_id};");
    }
  }

  /**
   * Process the order rearrangement commands
   */
  protected function processRearrangeCommand() {
    foreach (array('top', 'up', 'down', 'bottom') as $cmd) {
      $plugin_id = CRM_Utils_Request::retrieve($cmd, 'Integer');
      if (!$plugin_id) continue;

      $plugin_order = $this->getAllPluginSiblings($plugin_id);
      $original_plugin_order = $plugin_order;
      $index = array_search($plugin_id, $plugin_order);
      if ($index !== FALSE) {
        switch ($cmd) {
          case 'top':
            $new_index = 0;
            break;
          case 'up':
            $new_index = max(0, $index-1);
            break;
          case 'down':
            $new_index = min(count($plugin_order)-1, $index+1);
            break;
          default:
          case 'bottom':
            $new_index = count($plugin_order)-1;
            break;
        }
        // copied from https://stackoverflow.com/questions/12624153/move-an-array-element-to-a-new-index-in-php
        $out = array_splice($plugin_order, $index, 1);
        array_splice($plugin_order, $new_index, 0, $out);
      }

      // store the new plugin order
      if ($plugin_order != $original_plugin_order) {
        $this->storePluginOrder($plugin_order);
      }
    }
  }

  /**
   * Process export=$pid command by dumping a serialised version into the stream
   */
  protected function processExportCommand() {
    $plugin_id = CRM_Utils_Request::retrieve('export', 'Integer');
    if ($plugin_id) {
      $plugin_bao = new CRM_Banking_BAO_PluginInstance();
      $plugin_bao->get('id', $plugin_id);
      $exported_data = $plugin_bao->serialise();
      CRM_Utils_System::download(
          $plugin_bao->name . '.civibanking',
          'application/json',
          $exported_data);
    }


  }

  /**
   * get all plugins that are in the same class as the given one
   *
   * @return list of plugin IDs in order of weight
   */
  protected function getAllPluginSiblings($plugin_id) {
    $plugin_order = array();
    if (!$plugin_id) return $plugin_order;

    $query = CRM_Core_DAO::executeQuery("
      SELECT id AS plugin_id
        FROM civicrm_bank_plugin_instance
       WHERE plugin_type_id = (SELECT plugin_type_id FROM civicrm_bank_plugin_instance WHERE id = {$plugin_id})
       ORDER BY weight ASC");
    while ($query->fetch()) {
      $plugin_order[] = $query->plugin_id;
    }
    return $plugin_order;
  }

  /**
   * Will update the plugin's weights so it reflects the given order
   *
   * @return list of plugin IDs in order of weight
   */
  protected function storePluginOrder($plugin_order) {
    $weight = 10;
    foreach ($plugin_order as $plugin_id) {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_bank_plugin_instance SET weight={$weight} WHERE id = {$plugin_id}");
      $weight = $weight + 10;
    }
  }
}
