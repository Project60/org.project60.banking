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
    const STATUS_ELEMENT = 'status_select';

    /** Prefix for the key of the custom key-value-pair elements for searching in the data_parsed JSON field. */
    const CUSTOM_DATA_KEY_ELEMENT_PREFIX = 'custom_data_key_';
    /** Prefix for the value of the custom key-value-pair elements for searching in the data_parsed JSON field. */
    const CUSTOM_DATA_VALUE_ELEMENT_PREFIX = 'custom_data_value_';

    const CUSTOM_DATA_ELEMENTS_COUNT = 3;

    public function buildQuickForm()
    {
        $this->buildSearchElements();

        $this->addButtons(
            [
                [
                    'type' => 'submit',
                    'name' => E::ts('Find'),
                    'icon' => 'fa-search',
                    'isDefault' => true,
                ]
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
        // Custom search data elements:
        for ($i = 1; $i <= self::CUSTOM_DATA_ELEMENTS_COUNT; $i++) {
            $this->add(
                'text',
                self::CUSTOM_DATA_KEY_ELEMENT_PREFIX . $i,
                E::ts('Custom data key')
            );

            $this->add(
                'text',
                self::CUSTOM_DATA_VALUE_ELEMENT_PREFIX . $i,
                E::ts('Custom data value')
            );
        }

        $this->assign('customDataElementsCount', self::CUSTOM_DATA_ELEMENTS_COUNT);

    }

    public function postProcess()
    {
        parent::postProcess();
    }

    public static function getTransactionsAjax()
    {
        $ajaxParameters = CRM_Core_Page_AJAX::defaultSortAndPagerParams();
        $ajaxParameters += CRM_Core_Page_AJAX::validateParams(
            [], // No required parameters
            [
                self::VALUE_DATE_START_ELEMENT => 'String',
                self::VALUE_DATE_END_ELEMENT => 'String',
                self::BOOKING_DATE_START_ELEMENT => 'String',
                self::BOOKING_DATE_END_ELEMENT => 'String',
                self::MINIMUM_AMOUNT_ELEMENT => 'Integer',
                self::MAXIMUM_AMOUNT_ELEMENT => 'Integer',
                self::STATUS_ELEMENT => '', // FIXME: Array of String?
            ]
        );

        $queryParameters = [];
        $whereClauses = '';

        if (isset($ajaxParameters[self::VALUE_DATE_START_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND DATE(tx.value_date) >= DATE(%{$parameterCount})";

            $valueDateStart = $ajaxParameters[self::VALUE_DATE_START_ELEMENT];
            $queryParameters[$parameterCount] = [$valueDateStart, 'Date'];
        }
        if (isset($ajaxParameters[self::VALUE_DATE_END_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND DATE(tx.value_date) <= DATE(%{$parameterCount})";

            $valueDateEnd = $ajaxParameters[self::VALUE_DATE_END_ELEMENT];
            $queryParameters[$parameterCount] = [$valueDateEnd, 'Date'];
        }

        if (isset($ajaxParameters[self::BOOKING_DATE_START_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND DATE(tx.booking_date) >= DATE(%{$parameterCount})";

            $bookingDateStart = $ajaxParameters[self::BOOKING_DATE_START_ELEMENT];
            $queryParameters[$parameterCount] = [$bookingDateStart, 'Date'];
        }
        if (isset($ajaxParameters[self::BOOKING_DATE_END_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND DATE(tx.booking_date) <= DATE(%{$parameterCount})";

            $bookingDateEnd = $ajaxParameters[self::BOOKING_DATE_END_ELEMENT];
            $queryParameters[$parameterCount] = [$bookingDateEnd, 'Date'];
        }

        if (isset($ajaxParameters[self::MINIMUM_AMOUNT_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND tx.amount >= %{$parameterCount}";

            $minimumAmount = $ajaxParameters[self::MINIMUM_AMOUNT_ELEMENT];
            $queryParameters[$parameterCount] = [(int)$minimumAmount, 'Integer'];
        }
        if (isset($ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses += "AND tx.amount <= %{$parameterCount}";

            $maximumAmount = $ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT];
            $queryParameters[$parameterCount] = [(int)$maximumAmount, 'Integer'];
        }

        if (isset($ajaxParameters[self::STATUS_ELEMENT])) {
            // TODO: Implement

            //$parameterCount = count($queryParameters) + 1;

            //$whereClauses += "AND tx.status_id IN (%{$parameterCount})";

            //$status = $ajaxParameters[self::STATUS_ELEMENT]; // TODO: How to get the list of values/IDs?
            //$queryParameters[$parameterCount] = [$status, 'Integer'];
        }

        $sql =
        "SELECT
            tx.*,
            DATE(tx.value_date)      AS `date`,
            tx_status.name           AS status_name,
            tx_status.label          AS status_label,
            our_account.data_parsed  AS our_account_data,
            other_account.reference  AS other_account
        FROM
            civicrm_bank_tx AS tx
        LEFT JOIN
            civicrm_option_value AS tx_status
                ON
                    tx_status.id = tx.status_id
        LEFT JOIN
            civicrm_bank_account AS our_account
                ON
                    our_account.id = tx.ba_id
        LEFT JOIN
            civicrm_bank_account_reference AS other_account
                ON
                    other_account.id = tx.party_ba_id
        WHERE
            TRUE
            {$whereClauses}
        GROUP BY
            tx.id
        ORDER BY
            tx_status.weight,
            tx.value_date
        LIMIT 11"; // TODO: Remove the limit or handle it, e.g. with paging if really necessary.

        $transactionDao = CRM_Core_DAO::executeQuery($sql, $queryParameters);

        $results = [];
        while ($transactionDao->fetch()) {
            $results[] = [
                'date' => date('Y-m-d', strtotime($transactionDao->date)),
                'amount' => CRM_Utils_Money::format($transactionDao->amount, $transactionDao->currency),
                'status' => $transactionDao->status_label,
                'our_account'   => CRM_Utils_Array::value('name', json_decode($transactionDao->our_account, 1)), // todo cache?
                'other_account' => $transactionDao->other_account,
                // TODO: Contact
                // TODO: link?
                // TODO: Add more fields.
            ];
        }

        CRM_Utils_JSON::output(
            [
                'data'            => $results,
                'recordsTotal'    => count($results),
                'recordsFiltered' => count($results), // todo: correct value
            ]
        );
    }
}
