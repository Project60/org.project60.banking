<?php

declare(strict_types = 1);

/**
 * @covers CRM_Banking_Form_StatementSearch
 *
 * @group headless
 */
class CRM_Banking_Form_StatementSearchTest extends CRM_Banking_TestBase {

  /**
   * Transactions with the same value in the sort column must come back in a
   * fixed order, otherwise paging through the result table can show a
   * transaction twice and skip another one. The ID breaks the tie, in the
   * direction of the sort.
   */
  public function testTransactionsWithTheSameSortValueAreOrderedById(): void {
    // With only a few equal rows MySQL returns them in ID order even without a
    // tie-breaker (up to 16 rows on MySQL 8.4), so the test uses more.
    $transactionIds = [];
    for ($i = 0; $i < 20; $i++) {
      $transactionIds[] = $this->createTransaction(['booking_date' => '2026-11-01', 'amount' => 10]);
    }
    $descendingIds = array_reverse($transactionIds);

    // The default sort is the booking date, newest first.
    static::assertSame($descendingIds, $this->searchTransactionIds([]));
    static::assertSame($descendingIds, $this->searchTransactionIds($this->sortRequest('amount', 'desc')));
    static::assertSame($transactionIds, $this->searchTransactionIds($this->sortRequest('amount', 'asc')));
  }

  public function testTransactionsCanBeSortedByTheOtherAccount(): void {
    $firstAccountId = (int) $this->callAPISuccess('BankingAccount', 'create', ['data_parsed' => '{}'])['id'];
    $secondAccountId = (int) $this->callAPISuccess('BankingAccount', 'create', ['data_parsed' => '{}'])['id'];
    $secondAccountTransactionId = $this->createTransaction(['party_ba_id' => $secondAccountId]);
    $firstAccountTransactionId = $this->createTransaction(['party_ba_id' => $firstAccountId]);

    static::assertSame(
      [$firstAccountTransactionId, $secondAccountTransactionId],
      $this->searchTransactionIds($this->sortRequest('other_account', 'asc'))
    );
  }

  /**
   * Returns the DataTables parameters that sort the result table on a column.
   *
   * @return array<string, mixed>
   */
  private function sortRequest(string $column, string $direction): array {
    return [
      'columns' => [['data' => $column]],
      'order' => [['column' => '0', 'dir' => $direction]],
    ];
  }

  /**
   * Runs the AJAX request of the result table and returns the transaction IDs
   * of the returned rows, in their order.
   *
   * @param array<string, mixed> $request
   *   The DataTables parameters of the request.
   *
   * @return list<int>
   */
  private function searchTransactionIds(array $request): array {
    // The page reads its parameters from the request superglobals.
    // @phpstan-ignore-next-line
    [$originalGet, $originalRequest] = [$_GET, $_REQUEST];
    // @phpstan-ignore-next-line
    $_GET = $_REQUEST = $request;
    try {
      CRM_Banking_Form_StatementSearch::getTransactionsAjax();
    }
    // In test mode CiviCRM sends the JSON response by throwing this exception.
    // @phpstan-ignore catch.neverThrown
    catch (CRM_Core_Exception_PrematureExitException $exception) {
      return array_values(array_map(
        fn(array $row) => (int) preg_replace('/.*\[#(\d+)\].*/', '$1', $row['review_link']),
        $exception->errorData['data']
      ));
    }
    finally {
      // @phpstan-ignore-next-line
      [$_GET, $_REQUEST] = [$originalGet, $originalRequest];
    }
    static::fail('The statement search sent no response.');
  }

}
