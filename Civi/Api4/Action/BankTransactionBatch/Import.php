<?php

namespace Civi\Api4\Action\BankTransactionBatch;

use Civi\Api4\Generic\Result;

use CRM_Banking_ExtensionUtil as E;

/**
 * Import a statement
 */
class Import extends ImportBase {

  protected function process(array $statement, array $transactions, Result $result): void {
    // create a new batch / statement to add created transactions to
    try {
      $batch = \Civi\Api4\BankTransactionBatch::create(FALSE)
        ->setValues($statement)
        ->execute()
        ->single();
    }
    catch (\Throwable $e) {
      throw new \CRM_Core_Exception('Error creating new statement - does a matching statement already exist?');
    }

    $batchId = $batch['id'];

    foreach ($transactions as $tx) {
      try {
        \Civi\Api4\BankTransaction::create(FALSE)
          ->addValue('bank_reference', $tx['bank_reference'])
          ->addValue('booking_date', $tx['booking_date'])
          ->addValue('value_date', $tx['booking_date'])
          ->addValue('amount', $tx['amount'])
          ->addValue('data_parsed', \json_encode($tx['data_parsed']))
          ->addValue('data_raw', $tx['data_raw'])
          ->addValue('tx_batch_id', $batchId)
          ->addValue('status_id:name', 'new')
          // TOOD: is this field used? other importers seem to set fixed value 0
          ->addValue('type_id', 0)
          ->execute()
          ->single();
      }
      catch (\Throwable $e) {
        // row level error
        // common case  the transaction already exists
        $tx['error'] = $e->getMessage();
        $result['skipped']['error'][] = $tx;
        continue;
      }
    }

    $successfulTransactions = (array) \Civi\Api4\BankTransaction::get(FALSE)
      ->addWhere('tx_batch_id', '=', $batchId)
      ->addSelect('id', 'booking_date', 'amount', 'bank_reference', 'data_parsed')
      ->execute();

    if (!$successfulTransactions) {
      // every line failed. lets cleanup the empty batch and throw an error
      \Civi\Api4\BankTransactionBatch::delete(FALSE)
        ->addWhere('id', '=', $batchId)
        ->execute();

      throw new \CRM_Core_Exception("Failed to import any transactions:\n\n" . \json_encode($result['skipped'], \JSON_PRETTY_PRINT));
    }

    // decode data parsed for clientside
    $successfulTransactions = array_map(function ($tx) {
      $tx['data_parsed'] = \json_decode($tx['data_parsed'], TRUE);
      return $tx;
    }, $successfulTransactions);

    $result['statement'] = $batch;
    $result['transactions'] = $successfulTransactions;
  }

}
