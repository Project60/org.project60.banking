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
        '1' => E::ts("Simplified user interface"),
        '0' => E::ts("Traditional user interface")
    );
    $this->add(
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

    $this->add(
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

    $this->add(
      'select',
      'json_editor_mode',
      E::ts('Configuration Editor Default Mode'),
      $json_editor_mode,
      TRUE
    );

    // logging
    $this->add(
      'select',
      'banking_log_level',
      E::ts('Log Level'),
      CRM_Banking_Helpers_Logger::getLoglevels()
    );

    $this->add(
        'select',
        'recently_completed_cutoff',
        E::ts("Show Completed Statements (Recent)"),
        [
          0 => E::ts('disabled'),
          1 => E::ts('last month'),
          3 => E::ts('last quarter'),
          6 => E::ts('last %1 months', [1 => "6"]),
          12 => E::ts('last %1 months', [1 => "12"]),
          24 => E::ts('last %1 years', [1 => "2"]),
          60 => E::ts('last %1 years', [1 => "5"]),
        ],
        TRUE
    );

    $this->add(
      'text',
      'banking_log_file',
      E::ts('Log File'),
      ['class' => 'huge']
    );

    // store bank accounts
    $this->add(
      'checkbox',
      'reference_store_disabled',
      E::ts("Don't store bank accounts automatically"),
      '');

    // normalise bank account references?
    $this->add(
      'checkbox',
      'reference_normalisation',
      E::ts('Normalise bank account references'),
      '');

    // validate bank account references?
    $this->add(
      'checkbox',
      'reference_validation',
      E::ts('Validate bank account references'),
      '');
    // validate bank account references?
    $this->add(
      'text',
      'reference_matching_probability',
      E::ts('Probability of contact matching based on bank account'),
      '');
    $this->addRule('reference_matching_probability', E::ts('Not a valid number. A valid number is 1.0 or 0.9'), 'numeric');

    // validate bank account references?
    $this->add(
      'checkbox',
      'lenient_dedupe',
      E::ts('Lenient bank account dedupe'),
      '');

    // validate bank account references?
    $this->addElement(
      'text',
      CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF,
      E::ts('Transaction limit in view')
    );
    $this->addRule(
      CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF,
      E::ts('This needs to be a number larger than 0'),
      'positiveInteger'
    );

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
    $defaults = [];
    $defaults['new_ui']                          = Civi::settings()->get('new_ui');
    $defaults['menu_position']                   = Civi::settings()->get('menu_position');
    $defaults['json_editor_mode']                = Civi::settings()->get('json_editor_mode');
    $defaults['banking_log_level']               = Civi::settings()->get('banking_log_level');
    $defaults['banking_log_file']                = Civi::settings()->get('banking_log_file');
    $defaults['reference_store_disabled']        = Civi::settings()->get('reference_store_disabled');
    $defaults['reference_normalisation']         = Civi::settings()->get('reference_normalisation');
    $defaults['recently_completed_cutoff']       = Civi::settings()->get('recently_completed_cutoff');
    $defaults['reference_matching_probability']  = Civi::settings()->get('reference_matching_probability');
    $defaults['reference_validation']            = Civi::settings()->get('reference_validation');
    $defaults['lenient_dedupe']                  = Civi::settings()->get('lenient_dedupe');
    $defaults[CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF]
                                                 = Civi::settings()->get(CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF);

    if ($defaults['reference_matching_probability'] === null) {
      $defaults['reference_matching_probability'] = '1.0';
    }

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
      Civi::settings()->set('new_ui', $new_ui_style);
      Civi::settings()->set('menu_position', $new_menu_position);
      CRM_Core_BAO_Navigation::resetNavigation();
    }

    // process menu entry
    Civi::settings()->set('json_editor_mode', $new_menu_position);

    // log levels
    Civi::settings()->set('banking_log_level', $values['banking_log_level']);
    Civi::settings()->set('banking_log_file', $values['banking_log_file']);

    // process reference normalisation / validation
    Civi::settings()->set('reference_store_disabled', !empty($values['reference_store_disabled']));
    Civi::settings()->set('reference_normalisation', !empty($values['reference_normalisation']));
    Civi::settings()->set('reference_validation', !empty($values['reference_validation']));
    Civi::settings()->set('lenient_dedupe', !empty($values['lenient_dedupe']));
    Civi::settings()->set('reference_matching_probability', $values['reference_matching_probability']);
    Civi::settings()->set('recently_completed_cutoff', $values['recently_completed_cutoff']);

    // display settings
    Civi::settings()->set(CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF,
      $values[CRM_Banking_Config::SETTING_TRANSACTION_LIST_CUTOFF]);

    // log results
    $logger = CRM_Banking_Helpers_Logger::getLogger();
    $logger->logDebug("Log level changed to '{$values['banking_log_level']}', file is: {$values['banking_log_file']}");

    parent::postProcess();
  }
}
