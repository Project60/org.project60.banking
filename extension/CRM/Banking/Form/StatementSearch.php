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

/**
 * Search for transactions in civicrm_banking_tx by a lot of possible parameters.
 *  ("StatementSearch" is a misnomer)
 */
class CRM_Banking_Form_StatementSearch extends CRM_Core_Form
{
    const VALUE_DATE_START_ELEMENT = 'value_date_start';
    const VALUE_DATE_END_ELEMENT = 'value_date_end';
    const BOOKING_DATE_START_ELEMENT = 'booking_date_start';
    const BOOKING_DATE_END_ELEMENT = 'booking_date_end';
    const MINIMUM_AMOUNT_ELEMENT = 'minimum_amount';
    const MAXIMUM_AMOUNT_ELEMENT = 'maximum_amount';
    const STATUS_ELEMENT = 'status_select';

    /** Prefixes for the key of the custom key-value-pair elements for searching in the data_parsed JSON field. */
    const CUSTOM_DATA_ELEMENTS_COUNT = 5;
    const CUSTOM_DATA_KEY_ELEMENT_PREFIX = 'custom_data_key_name_';
    const CUSTOM_DATA_KEY_LIST_ELEMENT_PREFIX = 'custom_data_key_list_';
    const CUSTOM_DATA_VALUE_ELEMENT_PREFIX = 'custom_data_value_';

    public function buildQuickForm()
    {
        $this->setTitle(E::ts("Find CiviBanking Transactions"));

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

        Civi::resources()->addScriptUrl(E::url('js/statement_search.js'));
        Civi::resources()->addVars('banking_txsearch_basic_fields', [
          self::VALUE_DATE_START_ELEMENT,
          self::VALUE_DATE_END_ELEMENT,
          self::BOOKING_DATE_START_ELEMENT,
          self::BOOKING_DATE_END_ELEMENT,
          self::MINIMUM_AMOUNT_ELEMENT,
          self::MAXIMUM_AMOUNT_ELEMENT,
          self::STATUS_ELEMENT,
        ]);
        parent::buildQuickForm();
    }

    private function buildSearchElements()
    {
        $this->add(
            'datepicker',
            self::VALUE_DATE_START_ELEMENT,
            E::ts('Value Date from'),
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
            E::ts('Booking Date from'),
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
        $this->addRule(self::MINIMUM_AMOUNT_ELEMENT, E::ts("Please enter a valid amount."), 'money');

        $this->add(
            'text',
            self::MAXIMUM_AMOUNT_ELEMENT,
            E::ts('Maximum amount')
        );
        $this->addRule(self::MAXIMUM_AMOUNT_ELEMENT, E::ts("Please enter a valid amount."), 'money');

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
            E::ts('Transaction Status'),
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



        // CUSTOM SEARCH DATA
        $suggested_values = $this->getCustomDataFieldSuggestions();
        for ($i = 1; $i <= self::CUSTOM_DATA_ELEMENTS_COUNT; $i++) {
          $this->add(
              'text',
              self::CUSTOM_DATA_KEY_ELEMENT_PREFIX . $i,
              E::ts('Custom data key'),
              ['placeholder' => E::ts("enter key here")]
          );
          $this->addRule(self::CUSTOM_DATA_KEY_ELEMENT_PREFIX . $i,
                          E::ts("Parameter names cannot contain spaces or special characters"),
                          'regex',
                    '/^[a-zA-Z_]+$/');

          $this->add(
              'select',
              self::CUSTOM_DATA_KEY_LIST_ELEMENT_PREFIX . $i,
              E::ts('Suggested field names'),
              $suggested_values
          );

          $this->add(
                'text',
                self::CUSTOM_DATA_VALUE_ELEMENT_PREFIX . $i,
                E::ts('Value'),
                ['class' => 'huge']
            );
        }

        $this->assign('customDataElementsCount', self::CUSTOM_DATA_ELEMENTS_COUNT);
    }

    /**
     * Get a list of suggestions for the custom fields
     *
     * @return array
     *   key => label
     */
    protected function getCustomDataFieldSuggestions() {
        return [
            ''                => E::ts('-select-'),
            'purpose'         => E::ts('Purpose (<code>purpose</code>)'),
            'contact_id'      => E::ts('Contact ID (<code>contact_id</code>)'),
            'name'            => E::ts('Name (<code>name</code>)'),
            'street_address'  => E::ts('Address (<code>street_address</code>)'),
            'city'            => E::ts('City (<code>city</code>)'),
            'postal_code'     => E::ts('Postal Code (<code>postal_code</code>)'),
            '_IBAN'           => E::ts('Your IBAN (<code>_IBAN</code>)'),
            '_BIC'            => E::ts('Your BIC (<code>_BIC</code>)'),
            '_party_IBAN'     => E::ts('Other\'s IBAN (<code>_party_IBAN</code>)'),
            '_party_BIC'      => E::ts('Other\'s BIC (<code>_party_BIC</code>)'),
            'cancel_reason'   => E::ts('Cancel Reason (<code>cancel_reason</code>)'),
            '__other__'       => E::ts('other'),
        ];
    }




    /**
     * Will be called by the the page's jquery data table
     */
    public static function getTransactionsAjax()
    {
        $optionalAjaxParameters = [
            self::VALUE_DATE_START_ELEMENT => 'String',
            self::VALUE_DATE_END_ELEMENT => 'String',
            self::BOOKING_DATE_START_ELEMENT => 'String',
            self::BOOKING_DATE_END_ELEMENT => 'String',
            self::MINIMUM_AMOUNT_ELEMENT => 'Float',
            self::MAXIMUM_AMOUNT_ELEMENT => 'Float',
            self::STATUS_ELEMENT => 'CommaSeparatedIntegers',
        ];

        // Custom search data elements:
        $custom_parameter_list = CRM_Utils_Array::value('custom_parameters', $_REQUEST, '');
        $custom_parameter_list = explode(',', $custom_parameter_list);
        foreach ($custom_parameter_list as $custom_parameter) {
            $optionalAjaxParameters[$custom_parameter] = 'String';
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
                $sortBy = 'tx.ba_id';
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
            case 'review_link':
                $sortBy = 'tx.id';
                break;
            case '': // default
              $sortBy = 'tx.booking_date';
              $sortByComponents[1] = 'DESC';
              break;

            default:
                $sortBy = 'tx.booking_date';
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
            $queryParameters[$parameterCount] = [$minimumAmount, 'Float'];
        }
        if (isset($ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT])) {
            $parameterCount = count($queryParameters) + 1;

            $whereClauses[] = "AND tx.amount <= %{$parameterCount}";

            $maximumAmount = $ajaxParameters[self::MAXIMUM_AMOUNT_ELEMENT];
            $queryParameters[$parameterCount] = [$maximumAmount, 'Float'];
        }

        if (!empty($ajaxParameters[self::STATUS_ELEMENT])) {
            $statuses = explode(',', $ajaxParameters[self::STATUS_ELEMENT]);

            $parameters = [];

            $statusesCount = count($statuses);
            foreach ($statuses as $status) {
                $parameterCount = count($queryParameters) + 1;
                $queryParameters[$parameterCount] = [(int) $status, 'Integer'];
                $parameters[] = "%{$parameterCount}";
            }

            $parametersAsString = implode(',', $parameters);
            $whereClauses[] = "AND tx.status_id IN ({$parametersAsString})";
        }

        // Custom search data elements:
        foreach ($custom_parameter_list as $custom_parameter_name) {
            if (!empty($custom_parameter_name) && !empty($ajaxParameters[$custom_parameter_name])) {
                if (self::database_supports_json()) {
                  $parameterCount = count($queryParameters) + 2;
                  $firstParameterNumber = $parameterCount - 1;
                  $secondParameterNumber = $parameterCount;

                  $whereClauses[] = "AND JSON_UNQUOTE(JSON_EXTRACT(tx.data_parsed, %{$firstParameterNumber})) LIKE %{$secondParameterNumber}";
                  $queryParameters[$firstParameterNumber] = ["$.{$custom_parameter_name}", 'String'];
                  $queryParameters[$secondParameterNumber] = ["%{$ajaxParameters[$custom_parameter_name]}%", 'String'];

                } else {
                  $parameter_number = count($queryParameters) + 1;

                  $whereClauses[] = "AND tx.data_parsed LIKE %{$parameter_number}";
                  $queryParameters[$parameter_number] = ["%\"{$custom_parameter_name}\":\"%{$ajaxParameters[$custom_parameter_name]}%\"%", 'String'];
                }
            }
        }

        $whereClausesAsString = implode("\n", $whereClauses);

        $data_sql_query =
        "SELECT
            tx.*,
            DATE(tx.value_date)       AS `date`,
            tx_status.name            AS status_name,
            tx_status.label           AS status_label,
            our_account.id            AS our_account_id,
            our_account.data_parsed   AS our_account_data,
            GROUP_CONCAT(CONCAT(our_account_reference.reference, ',', our_account_reference.reference_type_id))
                                      AS our_account_references,
            other_account.id          AS other_account_id,
            other_account.data_parsed AS other_account_data,
            GROUP_CONCAT(CONCAT(other_account_reference.reference, ',', other_account_reference.reference_type_id))
                                      AS other_account_references
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
            civicrm_bank_account_reference AS our_account_reference
                ON
                    our_account_reference.ba_id = tx.ba_id
        LEFT JOIN
            civicrm_bank_account AS other_account
                ON
                    other_account.id = tx.party_ba_id
        LEFT JOIN
            civicrm_bank_account_reference AS other_account_reference
                ON
                    other_account_reference.ba_id = tx.party_ba_id
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

      $count_sql_query = "
        SELECT COUNT(DISTINCT(tx.id))
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
            civicrm_bank_account_reference AS our_account_reference
                ON
                    our_account_reference.id = tx.ba_id
        LEFT JOIN
            civicrm_bank_account_reference AS other_account
                ON
                    other_account.id = tx.party_ba_id
        WHERE
            TRUE
            {$whereClausesAsString}";

      CRM_Core_DAO::disableFullGroupByMode();
        $transaction_count = CRM_Core_DAO::singleValueQuery($count_sql_query, $queryParameters);
        $transactionDao = CRM_Core_DAO::executeQuery($data_sql_query, $queryParameters);
        CRM_Core_DAO::reenableFullGroupByMode();

        $results = [];
        while ($transactionDao->fetch()) {
            // preprocessing:
//            $our_account_data = json_decode($transactionDao->our_account_data, true);
//            $our_account = empty($our_account_data['name']) ? $transactionDao->our_account_reference : $our_account_data['name'];
            $data_parsed = json_decode($transactionDao->data_parsed, true);
            $purpose = trim(CRM_Utils_Array::value('purpose', $data_parsed, ''));
            $review_link = CRM_Utils_System::url('civicrm/banking/review', "id={$transactionDao->id}");

            $results[] = [
                'date'          => date('Y-m-d', strtotime($transactionDao->date)),
                'amount'        => CRM_Utils_Money::format($transactionDao->amount, $transactionDao->currency),
                'status'        => $transactionDao->status_label,
                'our_account'   => self::renderAccounts($transactionDao->our_account_id, $transactionDao->our_account_data,
                                                        $transactionDao->our_account_references, $data_parsed, true),
                'other_account' => self::renderAccounts($transactionDao->other_account_id, $transactionDao->other_account_data,
                                                        $transactionDao->other_account_references, $data_parsed, false),
                'purpose'       => '<span style="white-space: pre-wrap">' . $purpose . '</span>',
                'review_link'   => E::ts('<a href="%1" class="crm-popup">[#%2]</a>', [1 => $review_link, 2 => $transactionDao->id]),
            ];
        }

        CRM_Utils_JSON::output(
            [
                'data'            => $results,
                'recordsTotal'    => $transaction_count,
                'recordsFiltered' => $transaction_count,
            ]
        );
    }

  /**
   * Render the bank account information for the given resource data
   *
   * @param integer $account_id
   *   ID of the banking account
   * @param string $account_data
   *   data_parsed section of the account
   * @param string $account_references
   *   reference/ref_id list
   * @param array $tx_data
   *   the transactions data parsed array
   * @param boolean $is_own
   *   are we rendering our own account, or a party one?
   *
   * @return string HTML representation
   */
    public static function renderAccounts($account_id, $account_data, $account_references, $tx_data, $is_own)
    {
      // collect a list of linked accounts. keys: reference, type, remark
      $linked_accounts = [];

      // first though, load the bank account reference type list
      static $reference_types = null;
      if ($reference_types === null) {
        $reference_types = [];
        $ref_type_query = civicrm_api3('OptionValue', 'get', [
            'option_group_id' => 'civicrm_banking.reference_types',
            'option.limit'    => 0,
            'return'          => 'id,label,name'
        ]);
        foreach ($ref_type_query['values'] as $type) {
          // index by name and id
          $reference_types[$type['name']] = $type['label'];
          $reference_types[$type['id']] = $type['label'];
        }
      }


      // step1: render the linked accounts
      if ($account_id) {
        static $render_cache = []; // cache this by account id
        if (isset($render_cache[$account_id])) {
          $linked_accounts = $render_cache[$account_id];
        } else {
          // prepare the account names
          $account_data = json_decode($account_data, true);

          // parse the references
          $linked_accounts = [];
          $account_reference_list = explode(',', $account_references);
          for ($i = 1; $i < count($account_reference_list); $i+=2) {
            $reference = $account_reference_list[$i-1];
            if (empty($account_data['name'])) {
              $linked_accounts[$reference] = [
                  'reference' => $reference,
                  'type'      => $reference_types[$account_reference_list[$i]],
                  'remark'    => '',
              ];
            } else {
              $linked_accounts[$reference] = [
                  'reference' => $account_data['name'],
                  'type'      => $reference,
                  'remark'    => '',
              ];
              break; // one is enough, it's a known account
            }
          }
          $render_cache[$account_id] = $linked_accounts;
        }
      }

      // step2: add the soft accounts from the tx_data
      $prefix = ($is_own ? '_' : '_party_');
      foreach ($reference_types as $name => $type) {
        if (!is_int($name)) {
          $key = $prefix . $name;
          if (!empty($tx_data[$key])) {
            $reference = $tx_data[$key];
            if (!isset($linked_accounts[$reference])) {
              $linked_accounts[$reference] = [
                  'reference' => $reference,
                  'type'      => $type,
                  'remark'    => '<i title="' . E::ts("not linked") . '" class="crm-i fa-chain-broken"></i>',
              ];
            }
          }
        }
      }

      // step 3: render the accounts
      $rendered_string = '';
      foreach ($linked_accounts as $linked_account) {
        $rendered_string .= "<span title='{$linked_account['type']}'><code>{$linked_account['reference']}</code> {$linked_account['remark']}</span><br/>";
      }

      return $rendered_string;
    }

    /**
     * Check if the DB supports the JSON commands
     * @return boolean
     */
    public static function database_supports_json() {
        static $supported = null;
        if ($supported === null) {
            $version = CRM_Core_DAO::getGlobalSetting('version');
            if (strstr($version, 'MariaDB')) {
                $supported = version_compare($version,"10.2.3", '>=');
            } else {
                $supported = version_compare($version,"5.7", '>=');
            }
        }
        return $supported;
    }
}
