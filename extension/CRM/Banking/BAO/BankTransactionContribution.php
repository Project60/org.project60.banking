<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
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

/**
 * Class contains functions for CiviBanking plugin instances
 */
class CRM_Banking_BAO_BankTransactionContribution extends CRM_Banking_DAO_BankTransactionContribution {

  /**
   * @param array  $params
   *  (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Banking_DAO_BankTransactionContribution object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransactionContribution', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankTransactionContribution();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransactionContribution', $dao->id, $dao);
    return $dao;
  }

  /**
   * Link the given contribution to the given bank transaction
   *
   * @param integer $bank_tx_id
   * @param integer $contribution_id
   */
  public static function linkContribution($bank_tx_id, $contribution_id)
  {
    // don't check whether already linked, let the DB do that...
    CRM_Core_DAO::executeQuery(
        "INSERT IGNORE INTO civicrm_bank_tx_contribution (bank_tx_id,contribution_id) VALUES (%1, %2);",
        [
          1 => [$bank_tx_id, 'Integer'],
          2 => [$contribution_id, 'Integer'],
        ]
    );
  }

  /**
   * Create a queueable task to extract the linked contribution IDs from the suggestions field
   *
   * @param integer $from_tx_id  transaction ID to start with
   * @param integer $batch_size          (maximal) batch size, i.e. process tx with IDs between
   *                                $from_tx_id and $from_tx_id + $batch_size - 1
   *
   * @return object
   */
  public static function migrationTask($from_tx_id, $batch_size)
  {
    return new class($from_tx_id, $from_tx_id + $batch_size - 1) {
      public $title;
      private $from_tx_id;
      private $to_tx_id;
      private $status_ids;

      public function __construct($from_tx_id, $to_tx_id)
      {
        $this->title = E::ts("Migrated transactions %1 - %2", [1 => $from_tx_id, 2 => $to_tx_id]);
        $this->from_tx_id = $from_tx_id;
        $this->to_tx_id = $to_tx_id;
        $this->status_ids = implode(self::getTxStatusIDs());
      }

      /**
       * Run the transaction migration task
       *
       * @param $context    CRM_Queue_TaskContext
       * @return bool       success
       */
      public function run($context)
      {
        if (empty($this->status_ids)) {
          $context->log->err("no status IDs set, this should have been caught before");
          return false;
        }

        // run a query to the the suggestion strings
        CRM_Core_DAO::executeQuery("
            SELECT
              id          AS tx_id,
              suggestions AS suggestions
            FROM civicrm_bank_tx bank_tx
            WHERE bank_tx.id >= %1
              AND bank_tx.id <= %2
              AND bank_tx.status_id IN (%3)
          ", [
            1 => [$this->from_tx_id, 'Integer'],
            2 => [$this->to_tx_id,   'Integer'],
            3 => [$this->status_ids, 'CommaSeparatedIntegers'],
        ]);

        // todo: parse suggestions, extract contribution IDs, link

        return true;
      }

      /**
       * Get a list of 'completed' status IDs that need to be migrated
       */
      static function getTxStatusIDs()
      {
        static $status_list = null;
        if ($status_list === null) {
          $status_list = [];
          // add processed status
          $processed_status_id = banking_helper_optionvalueid_by_groupname_and_name(
            'civicrm_banking.bank_tx_status',
            'Processed'
          );
          if ($processed_status_id) {
            $status_list[] = $processed_status_id;
          }

          // todo: add more statuses? ignored?
        }
        return $status_list;
       }
    };
  }
}

