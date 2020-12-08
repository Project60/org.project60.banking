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

        // TODO: ba_id (receiver/target account) and party_ba_id (sender/party account)
        // How should the input look like? A select? Where to get the data from? civicrm_bank_account has way too
        // many entries. A text input? What to write into it?

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

    public static function getTransactionsAjax()
    {
        $optionalAjaxParameters = [
            self::VALUE_DATE_START_ELEMENT => 'String',
            self::VALUE_DATE_END_ELEMENT => 'String',
            self::BOOKING_DATE_START_ELEMENT => 'String',
            self::BOOKING_DATE_END_ELEMENT => 'String',
            self::MINIMUM_AMOUNT_ELEMENT => 'Integer',
            self::MAXIMUM_AMOUNT_ELEMENT => 'Integer',
            self::STATUS_ELEMENT => 'CommaSeparatedIntegers',
        ];

        // Custom search data elements:
        for ($i = 1; $i <= self::CUSTOM_DATA_ELEMENTS_COUNT; $i++) {
            $optionalAjaxParameters[self::CUSTOM_DATA_KEY_ELEMENT_PREFIX . $i] = 'String';
            $optionalAjaxParameters[self::CUSTOM_DATA_VALUE_ELEMENT_PREFIX . $i] = 'String';
        }

        $ajaxParameters = CRM_Core_Page_AJAX::defaultSortAndPagerParams();
        $ajaxParameters += CRM_Core_Page_AJAX::validateParams([], $optionalAjaxParameters);

        $sortByComponents = explode(' ', $ajaxParameters['sortBy'], 3);

        $sortBy = '';
        switch ($sortByComponents[0]) {
            case 'date':
                $sortBy = 'date';
                break;
            case 'our_account':
                $sortBy = 'our_account';
                break;
            case 'other_account':
                $sortBy = 'other_account';
                break;
            case 'amount':
                $sortBy = 'tx.amount';
                break;
            case 'status':
                $sortBy = 'status_label';
                break;
            case 'contact':
                $sortBy = 'contact_id';
                break;
            case 'review_link':
                $sortBy = 'tx.id';
                break;
            default:
                $sortBy = 'tx_status.weight, tx.value_date';
        }
        // TODO: Maybe this could be simplified by something like a
        //       "in_array($sortByComponents[0], ['date', 'our_account'])
        //       if the names in the parameter were equal to the ones in the SQL statement.

        if (strtoupper($sortByComponents[1]) == 'DESC') {
            $sortBy .= ' DESC';
        }

        $queryParameters = [
            1 => [$ajaxParameters['rp'], 'Integer'],
            2 => [$ajaxParameters['offset'], 'Integer'],
        ];
        $whereClauses = [];

        if (!empty($ajaxParameters[self::VALUE_DATE_START_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND DATE(tx.value_date) >= DATE(%{$parameterCount})";

            $dateTime = new DateTime($ajaxParameters[self::VALUE_DATE_START_ELEMENT]);
            $valueDateStart = $dateTime->format('Ymd');
            $queryParameters[$parameterCount] = [$valueDateStart, 'Date'];
        }
        if (!empty($ajaxParameters[self::VALUE_DATE_END_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND DATE(tx.value_date) <= DATE(%{$parameterCount})";

            $dateTime = new DateTime($ajaxParameters[self::VALUE_DATE_END_ELEMENT]);
            $valueDateEnd = $dateTime->format('Ymd');
            $queryParameters[$parameterCount] = [$valueDateEnd, 'Date'];
        }

        if (!empty($ajaxParameters[self::BOOKING_DATE_START_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND DATE(tx.booking_date) >= DATE(%{$parameterCount})";

            $dateTime = new DateTime($ajaxParameters[self::BOOKING_DATE_START_ELEMENT]);
            $bookingDateStart = $dateTime->format('Ymd');
            $queryParameters[$parameterCount] = [$bookingDateStart, 'Date'];
        }
        if (!empty($ajaxParameters[self::BOOKING_DATE_END_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND DATE(tx.booking_date) <= DATE(%{$parameterCount})";

            $dateTime = new DateTime($ajaxParameters[self::BOOKING_DATE_END_ELEMENT]);
            $bookingDateEnd = $dateTime->format('Ymd');
            $queryParameters[$parameterCount] = [$bookingDateEnd, 'Date'];
        }

        if (isset($ajaxParameters[self::MINIMUM_AMOUNT_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND tx.amount >= %{$parameterCount}";

            $minimumAmount = $ajaxParameters[self::MINIMUM_AMOUNT_ELEMENT];
            $queryParameters[$parameterCount] = [(int)$minimumAmount, 'Integer'];
        }
        if (isset($ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND tx.amount <= %{$parameterCount}";

            $maximumAmount = $ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT];
            $queryParameters[$parameterCount] = [(int)$maximumAmount, 'Integer'];
        }

        if (!empty($ajaxParameters[self::STATUS_ELEMENT])) {
            $statuses = explode(',', $ajaxParameters[self::STATUS_ELEMENT]);

            $parameters = [];

            $statusesCount = count($statuses);
            for ($i = 0; $i < $statusesCount; $i++) {
                $position = $parameterCount + 1 + $i;

                $queryParameters[$position] = [$statuses[$i], 'Integer'];

                $parameters[] = "%{$position}";
            }

            $parametersAsString = implode(',', $parameters);

            $whereClauses[] = "AND tx.status_id IN ({$parametersAsString})";

            $parameterCount = count($queryParameters) + $statusesCount;
        }

        // Custom search data elements:
        for ($i = 1; $i <= self::CUSTOM_DATA_ELEMENTS_COUNT; $i++) {
            $keyParameterName = self::CUSTOM_DATA_KEY_ELEMENT_PREFIX . $i;
            $valueParameterName = self::CUSTOM_DATA_VALUE_ELEMENT_PREFIX . $i;

            if (!empty($ajaxParameters[$keyParameterName]) && isset($ajaxParameters[$valueParameterName])) {
                $parameterCount = count($queryParameters) + 2;
                $firstParameterNumber = $parameterCount - 1;
                $secondParameterNumber = $parameterCount;

                $whereClauses[] = "AND JSON_UNQUOTE(JSON_EXTRACT(tx.data_parsed, %{$firstParameterNumber})) = %{$secondParameterNumber}";

                $queryParameters[$firstParameterNumber] = ['$.' . $ajaxParameters[$keyParameterName], 'String'];
                $queryParameters[$secondParameterNumber] = [$ajaxParameters[$valueParameterName], 'String'];
            }
        }

        $whereClausesAsString = implode("\n", $whereClauses);

        $sql =
        "SELECT
            tx.*,
            DATE(tx.value_date) AS `date`,
            tx_status.name AS status_name,
            tx_status.label AS status_label,
            JSON_UNQUOTE(JSON_EXTRACT(our_account.data_parsed, '$.name')) AS our_account,
            other_account.reference AS other_account,
            JSON_UNQUOTE(JSON_EXTRACT(tx.data_parsed, '$.contact_id')) AS contact_id
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
            {$whereClausesAsString}
        GROUP BY
            tx.id
        ORDER BY
            {$sortBy}
        LIMIT
            %1
        OFFSET
            %2";

        $transactionDao = CRM_Core_DAO::executeQuery($sql, $queryParameters);

        $results = [];
        while ($transactionDao->fetch()) {
            $results[] = [
                'date' => date('Y-m-d', strtotime($transactionDao->date)),
                'amount' => CRM_Utils_Money::format($transactionDao->amount, $transactionDao->currency),
                'status' => $transactionDao->status_label,
                'our_account' => $transactionDao->our_account,
                'other_account' => $transactionDao->other_account,
                'contact' => $transactionDao->contact_id,
                'review_link' => CRM_Utils_System::url('civicrm/banking/review/', ['id' => $transactionDao->id]),
                // TODO: The contact ID is not very useful here. What is the normal way to present a contact reference?
                // TODO: Add more fields?
            ];
        }

        CRM_Utils_JSON::output(
            [
                'data'            => $results,
                'recordsTotal'    => count($results), // TODO: Which is the correct value?
                'recordsFiltered' => count($results), // TODO: Which is the correct value?
            ]
        );
    }
}
