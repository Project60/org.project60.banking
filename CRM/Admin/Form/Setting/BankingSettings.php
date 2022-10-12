<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

require_once 'CRM/Core/Form.php';
use CRM_Banking_ExtensionUtil as E;

/**
 * CiviBanking settings form
 *
 * @todo refactor to use CRM_Core_Form correctly
 */
class CRM_Admin_Form_Setting_BankingSettings extends CRM_Core_Form {


  function buildQuickForm() {
    // add new UI (#200) options
    $ui_options = array(
        '1' => E::ts("New user interface"),
        '0' => E::ts("Legacy user interface")
    );
    $new_ui = $this->add(
        'select',
        'new_ui',
        E::ts('Statement User interface'),
        $ui_options,
        false // is not required
    );

    // add Menu Switch
    $menu_position_options = array(
      0 => E::ts("In Main Menu"),
      1 => E::ts("In Contribution Menu"),
      2 => E::ts("No Menu"));

    $menu_position = $this->add(
      'select',
      'menu_position',
      E::ts('CiviBanking Menu Position'),
      $menu_position_options,
      false // is not required
    );

    // add JSON editor mode
    $json_editor_mode = array(
      'view' => E::ts("Tree View"),
      'text' => E::ts("Simple Text"),
      'code' => E::ts("JSON Code"),
      'tree' => E::ts("Tree Editor"),
      'form' => E::ts("Form Editor"),
    );

    $json_editor_mode = $this->add(
      'select',
      'json_editor_mode',
      E::ts('Configuration Editor Default Mode'),
      $json_editor_mode,
      TRUE
    );

    // logging
    $log_level = $this->add(
      'select',
      'banking_log_level',
      E::ts('Log Level'),
      CRM_Banking_Helpers_Logger::getLoglevels()
    );

    $log_file = $this->add(
      'text',
      'banking_log_file',
      E::ts('Log File'),
      "TEST"
    );

    // store bank accounts
    $this->addElement(
      'checkbox',
      'reference_store_disabled',
      E::ts("Don't store bank accounts automatically"),
      '');

    // normalise bank account references?
    $this->addElement(
      'checkbox',
      'reference_normalisation',
      E::ts('Normalise bank account references'),
      '');

    // validate bank account references?
    $this->addElement(
      'checkbox',
      'reference_validation',
      E::ts('Validate bank account references'),
      '');
    // validate bank account references?
    $this->addElement(
      'text',
      'reference_matching_probability',
      E::ts('Probability of contact matching based on bank account'),
      '');
    $this->addRule('reference_matching_probability', E::ts('Not a valid number. A valid number is 1.0 or 0.9'), 'numeric');

    // validate bank account references?
    $this->addElement(
      'checkbox',
      'lenient_dedupe',
      E::ts('Lenient bank account dedupe'),
      '');


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts("Save"),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }


  /**
   * Overridden parent method to set default values
   * @return array
   */
  function setDefaultValues() {
    $defaults = array();

    $defaults['new_ui']                   = CRM_Core_BAO_Setting::getItem('CiviBanking', 'new_ui');
    $defaults['menu_position']            = CRM_Core_BAO_Setting::getItem('CiviBanking', 'menu_position');
    $defaults['json_editor_mode']         = CRM_Core_BAO_Setting::getItem('CiviBanking', 'json_editor_mode');
    $defaults['banking_log_level']        = CRM_Core_BAO_Setting::getItem('CiviBanking', 'banking_log_level');
    $defaults['banking_log_file']         = CRM_Core_BAO_Setting::getItem('CiviBanking', 'banking_log_file');
    $defaults['reference_store_disabled'] = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_store_disabled');
    $defaults['reference_normalisation']  = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_normalisation');
    $defaults['reference_matching_probability']     = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_matching_probability');
    if ($defaults['reference_matching_probability'] === null) {
      $defaults['reference_matching_probability'] = '1.0';
    }
    $defaults['reference_validation']     = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_validation');
    $defaults['lenient_dedupe']           = CRM_Core_BAO_Setting::getItem('CiviBanking', 'lenient_dedupe');

    return $defaults;
  }


  /**
   * store settings
   */
  function postProcess() {
    $values = $this->exportValues();

    // process menu relevant entries
    $old_menu_position = (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'menu_position');
    $new_menu_position = (int) $values['menu_position'];

    $old_ui_style = (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'new_ui');
    $new_ui_style = (int) $values['new_ui'];

    if ($old_menu_position != $new_menu_position || $old_ui_style != $new_ui_style) {
      CRM_Core_BAO_Setting::setItem($new_ui_style, 'CiviBanking', 'new_ui');
      CRM_Core_BAO_Setting::setItem($new_menu_position, 'CiviBanking', 'menu_position');
      CRM_Core_BAO_Navigation::resetNavigation();
    }

    // process menu entry
    CRM_Core_BAO_Setting::setItem($values['json_editor_mode'], 'CiviBanking', 'json_editor_mode');

    // log levels
    CRM_Core_BAO_Setting::setItem($values['banking_log_level'], 'CiviBanking', 'banking_log_level');
    CRM_Core_BAO_Setting::setItem($values['banking_log_file'],  'CiviBanking', 'banking_log_file');

    // process reference normalisation / validation
    CRM_Core_BAO_Setting::setItem(!empty($values['reference_store_disabled']),'CiviBanking', 'reference_store_disabled');
    CRM_Core_BAO_Setting::setItem(!empty($values['reference_normalisation']), 'CiviBanking', 'reference_normalisation');
    CRM_Core_BAO_Setting::setItem($values['reference_matching_probability'], 'CiviBanking', 'reference_matching_probability');
    CRM_Core_BAO_Setting::setItem(!empty($values['reference_validation']),    'CiviBanking', 'reference_validation');
    CRM_Core_BAO_Setting::setItem(!empty($values['lenient_dedupe']),          'CiviBanking', 'lenient_dedupe');

    // log results
    $logger = CRM_Banking_Helpers_Logger::getLogger();
    $logger->logDebug("Log level changed to '{$values['banking_log_level']}', file is: {$values['banking_log_file']}");

    parent::postProcess();
  }
}
