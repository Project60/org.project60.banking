<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2021 SYSTOPIA                            |
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
 * This is a queue runner to migrate the
 *   legacy contribution link data
 */
class CRM_Banking_Helpers_ContributionLinkMigration
{

  public $title;
  private $from_tx_id;
  private $to_tx_id;
  private $status_ids;

  public function __construct($from_tx_id, $batch_size)
  {
    $this->from_tx_id = $from_tx_id;
    $this->to_tx_id   = $this->from_tx_id + $batch_size - 1;
    $this->title      = E::ts("Migrated transactions %1 - %2", [1 => $this->from_tx_id, 2 => $this->to_tx_id]);
    $this->status_ids = implode(',', self::getTxStatusIDs());
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
    $batch = CRM_Core_DAO::executeQuery(
        "
            SELECT
              id          AS tx_id,
              suggestions AS suggestions
            FROM civicrm_bank_tx bank_tx
            WHERE bank_tx.id >= %1
              AND bank_tx.id <= %2
              AND bank_tx.status_id IN (%3)
          ",
        [
            1 => [(int) $this->from_tx_id, 'Integer'],
            2 => [(int) $this->to_tx_id, 'Integer'],
            3 => [$this->status_ids, 'CommaSeparatedIntegers'],
        ]
    );

    // migrate all of them. We have to use a heuristic to extract the linked
    //   contributions, because each matcher could do their own thing...
    $contribution_id_parameters = ['contribution_id', 'contribution_ids'];
    while ($batch->fetch()) {
      $suggestions = json_decode($batch->suggestions, true);
      foreach ($suggestions as $suggestion) {
        if (!empty($suggestion['executed'])) {
          // this suggestion has been executed -> find contribution_ids
          foreach ($contribution_id_parameters as $contribution_id_parameter) {
            if (!empty($suggestion[$contribution_id_parameter])) {
              $contribution_ids = $suggestion[$contribution_id_parameter];
              if (!is_array($contribution_ids)) {
                $contribution_ids = explode(',', (string) $contribution_ids);
              }
              foreach ($contribution_ids as $contribution_id) {
                $contribution_id = (int) $contribution_id;
                if ($contribution_id) {
                  CRM_Banking_BAO_BankTransactionContribution::linkContribution($batch->tx_id, $contribution_id);
                }
              }
            }
          }
        }
      }
    }

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
      $status_processed = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'civicrm_banking.bank_tx_status',
          'name'            => 'processed'
      ]);
      if (!empty($status_processed['id'])) {
        $status_list[] = (int) $status_processed['id'];
      }

      // todo: add more statuses? ignored?
    }
    return $status_list;
  }
}
