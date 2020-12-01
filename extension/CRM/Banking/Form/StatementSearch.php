<?php

/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Zschiedrich                                 |
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

class CRM_Banking_Form_StatementSearch extends CRM_Core_Form
{
    const VALUE_DATE_START_ELEMENT = 'value_date_start';
    const VALUE_DATE_END_ELEMENT = 'value_date_end';
    const BOOKING_DATE_START_ELEMENT = 'booking_date_start';
    const BOOKING_DATE_END_ELEMENT = 'booking_date_end';
    const MINIMUM_AMOUNT_ELEMENT = 'minimum_amount';
    const MAXIMUM_AMOUNT_ELEMENT = 'maximum_amount';
    const STATUS_ELEMENT = 'maximum_status';

    public function buildQuickForm()
    {
        // TODO: Aus data_parsed (JSON) suchen: Was denn?
        // -> Fünf Felder zur freien Eingabe von Parametername und Wert.

        $this->buildSearchElements();

        $this->addButtons(
            [
                'type' => 'find',
                'name' => E::ts('Find'),
                'icon' => 'fa-search',
                'isDefault' => true,
            ]
        );

        // Pass the AJAX URL to the Javascript frontend:
        CRM_Core_Resources::singleton()->addVars(
            'banking_transaction_search',
            [
                'data_url' => CRM_Utils_System::url('civicrm/banking/statements/search/data/'),
            ]
        );

        parent::buildQuickForm();
    }

    private function buildSearchElements()
    {
        // TODO: These two dates should be horizontally aligned with a '-' between them:
        $this->add(
            'datepicker',
            self::VALUE_DATE_START_ELEMENT,
            E::ts('Value Date start'),
            [
                'formatType' => 'activityDateTime'
            ]
        );
        $this->add(
            'datepicker',
            self::VALUE_DATE_END_ELEMENT,
            E::ts('Value Date end'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        // TODO: These two dates should be horizontally aligned with a '-' between them:
        $this->add(
            'datepicker',
            self::BOOKING_DATE_START_ELEMENT,
            E::ts('Booking Date start'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        $this->add(
            'datepicker',
            self::BOOKING_DATE_END_ELEMENT,
            E::ts('Booking Date end'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        // TODO: These two text fields should be horizontally aligned with a '-' between them:
        $this->add(
            'text',
            self::MINIMUM_AMOUNT_ELEMENT,
            E::ts('Minimum amount')
        );

        $this->add(
            'text',
            self::MAXIMUM_AMOUNT_ELEMENT,
            E::ts('Maximum amount')
        );

        $statusApi = civicrm_api3(
            'OptionValue',
            'get',
            [
                'option_group_id' => 'civicrm_banking.bank_tx_status',
                'options' => ['limit' => 0]
            ]
        );

        $statuses = [];
        foreach ($statusApi['values'] as $status) {
            $statuses[$status['id']] = $status['name'];
        }

        $this->add(
            'select',
            self::STATUS_ELEMENT,
            E::ts('Status'),
            $statuses,
            false,
            [
                'class' => 'crm-select2 huge',
                'multiple' => true,
            ]
        );

        // TODO: Which currency -> Is there a currency picker? -> Otherwise list picker
        // TODO: Which ba_id (receiver/target account) -> Look at how Banking does that!
        // TODO: Which party_ba_id (sender/party account) -> Look at how Banking does that!
    }

    private function buildButtons()
    {
        // TODO: Implement.
    }

    public function postProcess()
    {
        parent::postProcess();
    }

    public static function getTransactionsAjax()
    {
        CRM_Utils_JSON::output([]);
    }
}
