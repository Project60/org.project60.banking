<?php

/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich -at- systopia.de)  |
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
 * Shows an indicator if there is a rule match in the transaction summary.
 * This uses the hook "hook_civicrm_banking_transaction_summary".
 */
class CRM_Banking_RuleMatchIndicators
{
    /**
     * @var CRM_Banking_BAO_BankTransaction
     */
    private $transaction;

    /**
     * @var array
     */
    private $blocks;

    /**
     * @param CRM_Banking_BAO_BankTransaction $transaction
     * @param array $blocks
     */
    public function __construct($transaction, &$blocks)
    {
        $this->transaction = $transaction;
        $this->blocks = &$blocks;
    }

    /**
     *  add a matching indicator for the name
     */
    public function addContactMatchIndicator()
    {
        $contactName = $this->transaction->getDataParsed()['name'];
        if (empty($contactName)) {
          return;
        }

        $sql =
        "SELECT
            id
        FROM
            civicrm_bank_rules
        WHERE
            party_name = %1
        AND
            is_enabled = 1
        ";

        $parameters = [
            1 => [$contactName, 'String'],
        ];

        $ruleDao = CRM_Core_DAO::executeQuery($sql, $parameters);

        $result = $ruleDao->fetchAll();

        if (!empty($result)) {

          if (count($result) == 1) {
            $title = E::ts('Matching Rule');
          } else {
            $title = E::ts('%1 Matching Rules', [1 => count($result)]);
          }
          $url = CRM_Utils_System::url('civicrm/a/#/banking/rules/' . $result[0]['id']);
          $contactMatchIndicator = "&nbsp;<a target=\"_blank\" href=\"{$url}\"><i title=\"{$title}\" class=\"crm-i fa-info-circle\" aria-hidden=\"true\"></i></a>";

          $this->blocks['ReviewDebtor'] = str_replace(
                $contactName,
                $contactName . $contactMatchIndicator,
                $this->blocks['ReviewDebtor']
            );
        }
    }

    /**
     * Inject the bank reference matching indicator
     */
    public function addIbanMatchIndicator()
    {
        $party_ba_reference = $this->getPartyBankAccountReference($this->transaction->getDataParsed());
        if (empty($party_ba_reference)) {
            return;
        }

        $rules_search =
        "SELECT
            id
        FROM
            civicrm_bank_rules
        WHERE
            party_ba_ref = %1
        AND
            is_enabled = 1
        ";

        $parameters = [
            1 => [$party_ba_reference, 'String'],
        ];

        $ruleDao = CRM_Core_DAO::executeQuery($rules_search, $parameters);

        $result = $ruleDao->fetchAll();

        if (!empty($result)) {
            // Find the position after the IBAN to safely insert the indicator:
            $position = strpos($this->blocks['ReviewDebtor'], $party_ba_reference);
            $position = strpos($this->blocks['ReviewDebtor'], '</div>', $position) - 1;

            if (count($result) == 1) {
              $title = E::ts('Matching Rule');
            } else {
              $title = E::ts('%1 Matching Rules', [1 => count($result)]);
            }
            $url = CRM_Utils_System::url('civicrm/a/#/banking/rules/' . $result[0]['id']);
            $ibanMatchIndicator = "&nbsp;<a target=\"_blank\" href=\"{$url}\"><i title=\"{$title}\" class=\"crm-i fa-info-circle\" aria-hidden=\"true\"></i></a>";

            $this->blocks['ReviewDebtor'] =
                substr($this->blocks['ReviewDebtor'], 0, $position) .
                $ibanMatchIndicator .
                substr($this->blocks['ReviewDebtor'], $position);
        }
    }

    /**
     * Return the bank reference from the party,
     *   preferring IBAN
     *
     * @param array $data_parsed
     *   the transactions data
     *
     * @return string
     *   the reference
     */
    protected function getPartyBankAccountReference($data_parsed)
    {
        if (!empty($data_parsed['_party_IBAN'])) {
            return $data_parsed['_party_IBAN'];
        }

        foreach ($data_parsed as $parameter => $value) {
            if (!empty($value) && preg_match('/^party_NBAN_[A-Z]{2}^/', $parameter)) {
                return $value;
            }
        }

        // no reference found
        return null;
    }
}
