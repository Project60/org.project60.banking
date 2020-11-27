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
    public function buildQuickForm()
    {
        $this->add(
            'datepicker',
            'value_date_start',
            E::ts('Value Date start'),
            [
                'formatType' => 'activityDateTime'
            ]
        );
        $this->add(
            'datepicker',
            'value_date_end',
            E::ts('Value Date end'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        $this->add(
            'datepicker',
            'booking_date_start',
            E::ts('Booking Date start'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        $this->add(
            'datepicker',
            'booking_date_end',
            E::ts('Booking Date end'),
            [
                'formatType' => 'activityDateTime'
            ]
        );

        $this->add(
            'text',
            'minimum_amount',
            E::ts('Minimum amount')
        );

        $this->add(
            'text',
            'maximum_amount',
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
            'status',
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

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        parent::postProcess();
    }
}
