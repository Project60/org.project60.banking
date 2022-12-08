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

use CRM_Banking_ExtensionUtil as E;

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
    $this->assign('reference_types_json', json_encode($reference_types['values']));
    $reference_type_list = array();
    $this->assign('reference_types', $reference_types['values']);
    foreach ($reference_types['values'] as $reference_type_id => $reference_type) {
      $reference_type_list[$reference_type_id] = "{$reference_type['label']} ({$reference_type['name']})";
    }

    // load countries
    $country_id2iso  = CRM_Core_PseudoConstant::countryIsoCode();
    $country_iso2id  = array_flip($country_id2iso);
    $country_id2name = CRM_Core_PseudoConstant::country();
    $countries = array('' => E::ts("Unknown"));
    foreach ($country_iso2id as $iso => $id) {
        if (!empty($country_id2name[$id])) {
            $countries[$iso] = $country_id2name[$id];
        }
    }

    // load settings
    $this->assign('reference_normalisation', (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_normalisation'));
    $this->assign('reference_validation',    (int) CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_validation'));

    // ACCOUNT REFRENCE ITEMS
    $this->add('hidden', 'contact_id', $contact_id, true);
    $this->add('hidden', 'reference_id');

    $reference_type = $this->add(
        'select',
        'reference_type',
        E::ts("Bank Account Type"),
        $reference_type_list,
        true // is required
    );
    // set last value
    $reference_type->setSelected(CRM_Core_BAO_Setting::getItem('CiviBanking', 'account.default_reference_id'));

    $reference_type = $this->add(
        'text',
        'reference',
        E::ts("Bank Account Number"),
        array('size' => 40),
        true
    );

    
    // BANK ITEMS
    $this->add('hidden', 'ba_id');

    $this->addElement(
        'text',
        'bic',
        E::ts("BIC"),
        array('size' => 40),
        false
    );

    $this->addElement(
        'text',
        'bank_name',
        E::ts("Account Name"),
        array('size' => 40),
        false
    );

    $country = $this->add(
        'select',
        'country',
        E::ts("Country"),
        $countries,
        false
    );
    // set last value
    $country->setSelected(CRM_Core_BAO_Setting::getItem('CiviBanking', 'account.default_country'));


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
        'isDefault' => FALSE,
      )
    ));

    parent::buildQuickForm();
  }

  /**
   * form validate function => check date order
   */
  public function validate() {
    $error = parent::validate();
    $values = $this->exportValues();
    $normalise = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_normalisation');
    $validate  = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_validation');

    if (!empty($values['reference_type']) && !empty($values['reference'])) {
        if ($validate || $normalise) {
            // verify/normalise reference
            $query = civicrm_api3('BankingAccountReference', 'check', array(
                'reference_type' => (int) $values['reference_type'],
                'reference'      => $values['reference']));
            $result = $query['values'];
            if ($validate && $result['checked'] && !$result['is_valid']) {
                $this->_errors['reference'] = E::ts("Invalid reference.");
                CRM_Core_Session::setStatus(E::ts("Invalid reference '%1'", array(1=>$values['reference'])), E::ts('Failure'));
            } elseif ($normalise && $result['normalised'] ) {
                $values['reference'] = $result['reference'];
                $this->set('reference', $result['reference']);
            }            
        }
    }
    
    if (0 == count($this->_errors)) {
        return TRUE;
    } else {
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid=2&selectedChild=bank_accounts"));
        return FALSE;
    }
  }

  /**
   * Save presets and create/update account/reference
   */
  function postProcess() {
    $values = $this->exportValues();
    $was_created = FALSE;
    
    // save presets
    if (!empty($values['reference_type'])) {
        CRM_Core_BAO_Setting::setItem($values['reference_type'], 'CiviBanking', 'account.default_reference_id');
    }
    if (!empty($values['country'])) {
        CRM_Core_BAO_Setting::setItem($values['country'], 'CiviBanking', 'account.default_country');
    }
    
    // create bank account
    $ba_id = $values['ba_id'];
    if (empty($ba_id)) {
        $bank_account = civicrm_api3('BankingAccount', 'create', array(
            'contact_id'  => $values['contact_id'],
            'data_parsed' => '{}',
            ));
        $was_created = TRUE;
        $ba_id = $bank_account['id'];
    }

    // update bank account data
    $bank_data_attributes = array('bic' => 'BIC', 'bank_name' => 'name', 'country' => 'country');
    $bank_bao = new CRM_Banking_BAO_BankAccount();
    $bank_bao->get('id', $ba_id);
    $bank_data = $bank_bao->getDataParsed();
    foreach ($bank_data_attributes as $form_attribute => $bank_data_attribute) {
        if (empty($values[$form_attribute])) {
            unset($bank_data[$bank_data_attribute]);
        } else {
            $bank_data[$bank_data_attribute] = $values[$form_attribute];
        }
    }
    $bank_bao->setDataParsed($bank_data);
    $bank_bao->save();

    // update/create bank reference
    $reference_update = array(
        'reference'         => $values['reference'],
        'reference_type_id' => $values['reference_type'],
        'ba_id'             => $ba_id,
        );
    if (!empty($values['reference_id'])) {
        $reference_update['id'] = $values['reference_id'];
    }
    
    civicrm_api3('BankingAccountReference', 'create', $reference_update);

    if ($was_created) {
        CRM_Core_Session::setStatus(E::ts("Bank account '%1' was created.", array(1=>$values['reference'])), E::ts('Success'));
    } else {
        CRM_Core_Session::setStatus(E::ts("Bank account '%1' was updated.", array(1=>$values['reference'])), E::ts('Success'));
    }

    // return to accounts tab
    if (!empty($values['contact_id'])) {
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$values['contact_id']}&selectedChild=bank_accounts"));        
    }
    parent::postProcess();
  }

}
