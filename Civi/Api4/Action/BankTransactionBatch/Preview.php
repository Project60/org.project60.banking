<?php

namespace Civi\Api4\Action\BankTransactionBatch;

use Civi\Api4\Generic\Result;

/**
 * Preview import
 */
class Preview extends ImportBase {

  protected function process(array $statement, array $transactions, Result $result): void {
    $result['statement'] = $statement;

    $result['transactions'] = $transactions;

    // return params (so we can display autodetections in the UI)
    $result['params'] = [
      'dateColumn' => $this->dateColumn,
      'dateFormat' => $this->dateFormat,
      'referenceColumn' => $this->referenceColumn,
      'amountColumn' => $this->amountColumn,
    ];

    $result['headerColumns'] = array_keys($transactions[0]['data_parsed']);
    $result['dateFormats'] = $this->getDateFormats();
  }

}
