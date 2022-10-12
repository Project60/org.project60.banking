<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
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
class CRM_Banking_Form_PluginUpload extends CRM_Core_Form {


  public function buildQuickForm() {
    $pid = CRM_Utils_Request::retrieve('pid', 'Integer');
    $this->addElement('hidden', 'pid', $pid);
    if ($pid) {
      // this is a update
      $plugin = civicrm_api3('BankingPluginInstance', 'getsingle', array('id' => $pid));

      CRM_Utils_System::setTitle(E::ts("Update plugin '%1' with configuration file.", [1 => $plugin['name']]));
      $this->assign("is_import", 0);

      $this->addElement(
          'file',
          'config_files',
          E::ts('Select configuration file'),
          'accept=banking');

      $this->addButtons(array(
          array(
              'type' => 'submit',
              'name' => E::ts('Update'),
              'isDefault' => TRUE,
          ),
      ));

    } else {
      // this is an import
      CRM_Utils_System::setTitle(E::ts("Import CiviBanking Plugins"));
      $this->assign("is_import", 1);

      $this->addElement(
          'file',
          'config_files',
          E::ts('Select files to import'),
          'multiple accept=banking');

      $this->addButtons(array(
          array(
              'type' => 'submit',
              'name' => E::ts('Import'),
              'isDefault' => TRUE,
          ),
      ));
    }


    // export form elements
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    try {
      if (!empty($_FILES['config_files'])) {
        if (empty($values['pid'])) {
          // IMPORT a list of new plugins
          foreach ($_FILES['config_files']['tmp_name'] as $tmp_name) {
            $data = file_get_contents($tmp_name);
            $plugin_bao = new CRM_Banking_BAO_PluginInstance();
            $plugin_bao->updateWithSerialisedData($data);
          }

          CRM_Core_Session::setStatus(E::ts('%1 new plugins imported', [1 => count($_FILES['config_files']['tmp_name'])]), E::ts('Success'));

        } else {
          // UPDATE the given plugin
          $data = file_get_contents($_FILES['config_files']['tmp_name']);
          $plugin_bao = new CRM_Banking_BAO_PluginInstance();
          $plugin_bao->get('id', $values['pid']);
          $plugin_bao->updateWithSerialisedData($data, ['name'], ['plugin_type_name', 'plugin_class_name']);

          CRM_Core_Session::setStatus(E::ts('Plugin configuration updated'), E::ts('Success'));
        }
      } else {
        CRM_Core_Session::setStatus(E::ts('No configuration files selected'), E::ts('Error'));
      }
    } catch (Exception $ex) {
      CRM_Core_Session::setStatus(E::ts('Import/update failed: %1', [1 => $ex->getMessage()]), E::ts('Failed'));
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/banking/manager'));
    parent::postProcess();
  }
}

