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
}
