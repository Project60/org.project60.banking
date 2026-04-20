<?php

namespace Civi\Api4\Action\BankTransactionBatch;

use Civi\Api4\Generic\Result;

use CRM_Banking_ExtensionUtil as E;
use DateTimeImmutable;

/**
 * Fetch statement from a Google Sheet
 */
abstract class ImportBase extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var string
   *
   * Title of imported document
   */
  protected string $title = '';

  /**
   * @var array
   * @required
   *
   * Lines of imported document
   */
  protected array $content;

  /**
   * @var string
   * @required
   *
   * Format of imported document
   */
  protected string $contentFormat = 'csv';

  /**
   * @var string
   *
   * The column header corresponding to the transaction date - leave blank to auto-detect
   */
  protected string $dateColumn = '';

  /**
   * @var string
   *
   * The column header corresponding to the transaction reference - leave blank to auto-detect
   */
  protected string $referenceColumn = '';
  /**
   * @var string
   *
   * The column header corresponding to the transaction amount - leave blank to auto-detect
   */
  protected string $amountColumn = '';

  /**
   * @var string
   *
   * Date column format - leave blank to auto-detect
   */
  protected string $dateFormat = '';

  /**
   * @var int
   *
   * Which row is the header in - leave blank to auto-detect
   */
  protected ?int $headerIndex = NULL;

  /**
   * @var string
   * @required
   *
   * Thousands separator when parsing amounts
   * @todo inherit from `format_locale` by default
   */
  protected string $thousandsSeparator = ',';

  /**
   * @var string
   *
   * Imported statement title
   */
  protected string $statementTitle = '';

  public function _run(Result $result) {
    $sheet = match ($this->contentFormat) {
      'csv' => array_map(fn ($line) => \str_getcsv($line), $this->content),
      'xml' => throw new \CRM_Core_Exception('Sorry xml is not implemented yet'),
      default => throw new \CRM_Core_Exception('Unrecognised content format: ' . $this->contentFormat),
    };

    if (is_null($this->headerIndex) || !$this->dateColumn || !$this->amountColumn || !$this->referenceColumn) {
      $this->autodetectHeader($sheet);
    }

    $preHeader = array_slice($sheet, 0, $this->headerIndex);
    $reference = $preHeader ? implode(' - ', array_filter(array_merge(...$preHeader))) : NULL;

    $header = $sheet[$this->headerIndex];

    // get rows after header
    $rows = \array_slice($sheet, $this->headerIndex + 1);

    // filter rows where every cell is empty
    $rows = array_filter($rows, fn ($row) => array_filter($row, fn ($cell) => !is_null($cell)));

    // get parsed data from rows
    $transactions = array_map(function ($row) use ($header) {
      $data = [];
      foreach ($header as $i => $col) {
        $key = trim($col);
        $value = trim($row[$i]);

        if (isset($data[$key])) {
          $data[$key] = array_merge((array) $data[$key], [$value]);
        }
        else {
          $data[$key] = $value;
        }
      }
      return [
        'data_raw' => implode(";", $row),
        'data_parsed' => $data,
      ];
    }, $rows);

    if (!$this->dateFormat) {
      $this->autodetectDateFormat($transactions);
    }

    // parse data/amount/reference columns
    $transactions = array_map(function ($tx) {
      // parse reference
      $rawReference = $tx['data_parsed'][$this->referenceColumn];
      $tx['bank_reference'] = ($rawReference !== '') ? $rawReference : NULL;

      // parse amount to float
      // @todo could we autodetect thousands separator?
      $rawAmount = $tx['data_parsed'][$this->amountColumn];
      $tx['amount'] = ($rawAmount !== '') ? (float) \str_replace($this->thousandsSeparator, '', $rawAmount) : NULL;

      // parse date
      $tx['booking_date'] = $this->parseDate($this->dateFormat, $tx['data_parsed'][$this->dateColumn]);

      return $tx;
    }, $transactions);

    // filter out invalid rows
    $transactions = array_values(array_filter($transactions, function ($tx) use ($result) {
      // if no date this is an invalid row, skip it
      $required = [
        'booking_date' => $this->dateColumn,
        'amount' => $this->amountColumn,
        'bank_reference' => $this->referenceColumn,
      ];
      $missing = array_filter(array_keys($required), fn ($key) => is_null($tx[$key]));
      if ($missing) {
        $missingColumns = array_map(fn ($key) => $required[$key], $missing);
        $tx['error'] = E::ts("Missing or invalid column(s): %1", [1 => \implode(', ', $missingColumns)]);
        $result['skipped']['invalid'][] = $tx;
        return FALSE;
      }
      return TRUE;
    }));

    if (!$transactions) {
      throw new \CRM_Core_Exception("No valid transactions found");
    }

    $dates = \array_column($transactions, 'booking_date');
    $fromDate = min(...$dates);
    $toDate = max(...$dates);

    // if no reference was extracted from the sheet, we need to generate one
    if (!$reference && !$this->statementTitle) {
      $this->statementTitle = 'Imported Statement';
    }

    if ($this->statementTitle) {
      $countPrevious = \Civi\Api4\BankTransactionBatch::get(FALSE)
        ->addSelect('row_count')
        ->addWhere('reference', 'CONTAINS', $this->statementTitle)
        ->execute()
        ->count();

      $reference = "{$this->statementTitle} #{$countPrevious} from {$fromDate} to {$toDate}";
    }
    else {
      // TODO: how to detect this
      $countPrevious = 0;
    }

    $statement = [
      'issue_date' => $toDate,
      'reference' => $reference,
      'tx_count' => count($transactions),
      'starting_date' => $fromDate,
      'ending_date' => $toDate,
      'sequence' => $countPrevious,
    ];

    $this->process($statement, $transactions, $result);
  }

  abstract protected function process(array $statement, array $transactions, Result $result): void;

  /**
   * Test if a row could be the header
   *
   * If date/amount/reference column are specified, it must contain them
   * Otherwise we must be able to autodetect a column
   */
  protected function validHeader(array $header): bool {
    $dateColumn = $this->dateColumn ? in_array($this->dateColumn, $header) : array_find($header, fn ($col) => \str_contains($col, E::ts('Date')));
    $amountColumn = $this->amountColumn ? in_array($this->amountColumn, $header) : array_find($header, fn ($col) => \str_contains($col, E::ts('Amount')));
    $referenceColumn = $this->referenceColumn ? in_array($this->referenceColumn, $header) : array_find($header, fn ($col) => \str_contains($col, E::ts('Reference')));

    return $dateColumn && $amountColumn && $referenceColumn;
  }

  protected function autodetectHeader(array $sheet): void {
    // if no header index specified, find the first row that could be a header
    if (is_null($this->headerIndex)) {
      $this->headerIndex = array_find_key($sheet, fn ($row) => $this->validHeader($row));

      // no valid header row could be found
      if (is_null($this->headerIndex)) {
        throw new \CRM_Core_Exception("Unable to determine header");
      }
    }

    // get the header row
    $header = $sheet[$this->headerIndex];

    // use autodetect key columns values if needed
    $this->dateColumn = $this->dateColumn ?: array_find($header, fn ($col) => \str_contains($col, E::ts('Date')));
    $this->amountColumn = $this->amountColumn ?: array_find($header, fn ($col) => \str_contains($col, E::ts('Amount')));
    $this->referenceColumn = $this->referenceColumn ?: array_find($header, fn ($col) => \str_contains($col, E::ts('Reference')));

    // sense check: header should contain all key columns
    // this could fail if header and xColumns are specified that are inconsistent
    $missing = array_diff([$this->dateColumn, $this->amountColumn, $this->referenceColumn], $header);
    if ($missing) {
      $message = E::ts('Unable to find %1 in header row %2', [1 => implode(', ', $missing), 2 => $this->headerIndex]);
      throw new \CRM_Core_Exception($message);
    }
  }

  /**
   * Find the best date format
   *
   * NOTE: will leave dateFormat as NULL if
   */
  protected function autodetectDateFormat(array $transactions) {
    $dateValues = array_map(fn ($tx) => $tx['data_parsed'][$this->dateColumn], $transactions);

    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
    $bestScore = 0;

    foreach ($formats as $format) {
      // how many values can we parse
      $score = count(array_filter(array_map(fn ($value) =>
        $this->parseDate($format, $value), $dateValues)
      ));

      if ($score > $bestScore) {
        $bestScore = $score;
        $this->dateFormat = $format;
      }
    }

    if (!$this->dateFormat) {
      throw new \CRM_Core_Exception('Could not find a date format with any matches');
    }

  }

  protected function parseDate($format, $value): ?string {
    try {
      $date = DateTimeImmutable::createFromFormat($format, $value);
      $errors = DateTimeImmutable::getLastErrors();
      if ($date && !$errors) {
        return $date->format('Y-m-d');
      }
    }
    catch (\DateMalformedStringException $e) {
      // fall through to false
    }
    return NULL;
  }

}
