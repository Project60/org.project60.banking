<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Admin_Form_Setting_BankingSettings extends CRM_Core_Form {


  function buildQuickForm() {

    // add Menu Switch
    $menu_position_options = array(
      0 => ts("In Main Menu"),
      1 => ts("In Contribution Menu"),
      2 => ts("No Menu"));

    $menu_position = $this->add(
      'select',
      'menu_position',
      ts('CiviBanking Menu Position'),
      $menu_position_options,
      false // is not required
    );
    $menu_position->setSelected((int) $this->getCurrentValue('menu_position'));

    // logging
    $log_level = $this->add(
      'select',
      'banking_log_level',
      ts('Log Level'),
      CRM_Banking_Helpers_Logger::getLoglevels()
    );
    $log_level->setSelected($this->getCurrentValue('banking_log_level'));

    $log_file = $this->add(
      'text',
      'banking_log_file',
      ts('Log File'),
      "TEST"
    );




    // normalise bank account references?
    $this->addElement(
      'checkbox',
      'reference_normalisation',
      ts('Normalise bank account references'),
      '',
      ($this->getCurrentValue('reference_normalisation')?array('checked' => 'checked'):array()));

    // validate bank account references?
    $this->addElement(
      'checkbox',
      'reference_validation',
      ts('Validate bank account references'),
      '',
      ($this->getCurrentValue('reference_validation')?array('checked' => 'checked'):array()));


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts("Save"),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }


  function postProcess() {
    $values = $this->exportValues();

    // process menu entry
    $old_menu_position = (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'menu_position');
    $new_menu_position = (int) $values['menu_position'];
    if ($old_menu_position != $new_menu_position) {
      CRM_Core_BAO_Setting::setItem($new_menu_position, 'CiviBanking', 'menu_position');
      CRM_Core_Invoke::rebuildMenuAndCaches();
    }

    // log levels
    CRM_Core_BAO_Setting::setItem($values['banking_log_level'], 'CiviBanking', 'banking_log_level');
    CRM_Core_BAO_Setting::setItem($values['banking_log_file'],  'CiviBanking', 'banking_log_file');

    // process reference normalisation / validation
    CRM_Core_BAO_Setting::setItem(!empty($values['reference_normalisation']), 'CiviBanking', 'reference_normalisation');
    CRM_Core_BAO_Setting::setItem(!empty($values['reference_validation']),    'CiviBanking', 'reference_validation');

    // log results
    $logger = CRM_Banking_Helpers_Logger::getLogger();
    $logger->logDebug("Log level changed to '{$values['banking_log_level']}', file is: {$values['banking_log_file']}");

    parent::postProcess();
  }

  /**
   * Get the temporarily valid value
   *
   * @todo USE setDefaults() instead!
   */
  public function getCurrentValue($key) {
    if (!empty($this->_submitValues[$key])) {
      return $this->_submitValues[$key];
    } else {
      return CRM_Core_BAO_Setting::getItem('CiviBanking', $key);
    }
  }

}
