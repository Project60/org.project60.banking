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
class CRM_Banking_Form_AccountsTab extends CRM_Core_Form {

  function buildQuickForm() {
    $contact_id = 0;
    $bank_accounts = array();

    if (isset($_REQUEST['cid'])) {
      $contact_id = (int) $_REQUEST['cid'];

      $bank_account = new CRM_Banking_BAO_BankAccount();
      $bank_account->contact_id = $contact_id;
      $bank_account->find();

      while ($bank_account->fetch()) {
        $bank_account_data = $bank_account->toArray();
        $bank_account_data['references']  = $bank_account->getReferences();
        $bank_account_data['data_parsed'] = json_decode($bank_account->data_parsed, true);
        $bank_accounts[$bank_account->id] = $bank_account_data;
      }
    }

    $this->assign('bank_accounts', $bank_accounts);
    $this->assign('bank_accounts_json', json_encode($bank_accounts));
    $this->assign('contact_id', $contact_id);

    // load all account types
    $option_group    = civicrm_api3('OptionGroup', 'getsingle', array('name' => 'civicrm_banking.reference_types'));
    $reference_types = civicrm_api3('OptionValue', 'get', array('option_group_id' => $option_group['id'], 'is_reserved' => 0));
    $this->assign('reference_types_json', json_encode($reference_types));
    $reference_type_list = array();
    $this->assign('reference_types', $reference_types['values']);
    foreach ($reference_types['values'] as $reference_type_id => $reference_type) {
      $reference_type_list[$reference_type_id] = "{$reference_type['label']} ({$reference_type['name']})";
    }

    // load countries
    $country_id2iso  = CRM_Core_PseudoConstant::countryIsoCode();
    $country_iso2id  = array_flip($country_id2iso);
    $country_id2name = CRM_Core_PseudoConstant::country();
    $countries = array('' => ts("Unknown"));
    foreach ($country_iso2id as $iso => $id) {
        $countries[$iso] = $country_id2name[$id];
    }

    // ACCOUNT REFRENCE ITEMS
    $this->add('hidden', 'reference_id');

    $reference_type = $this->add(
        'select',
        'reference_type',
        ts("Bank Account Type"),
        $reference_type_list,
        true // is required
    );
    // FIXME: last value
    $reference_type->setSelected('IBAN'); 

    $reference_type = $this->add(
        'text',
        'reference',
        ts("Bank Account Number"),
        array('size' => 40),
        true
    );

    
    // BANK ITEMS
    $this->add('hidden', 'ba_id');

    $this->addElement(
        'text',
        'bic',
        ts("BIC"),
        array('size' => 40),
        false
    );

    $this->addElement(
        'text',
        'bank_name',
        ts("Bank Name"),
        array('size' => 40),
        false
    );

    $country = $this->add(
        'select',
        'country',
        ts("Country"),
        $countries,
        false
    );
    // FIXME: last value
    $country->setSelected('DE'); 


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'isDefault' => FALSE,
      )
    ));

    parent::buildQuickForm();
  }

  // TODO: VALIDATE

  /**
   * 
   */
  function postProcess() {
    $values = $this->exportValues();
    
    // TODO: save presets

    // TODO: create update ba/ba_ref
    error_log(print_r($values,1));

    parent::postProcess();
  }

}
