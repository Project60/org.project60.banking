<?php
declare(strict_types = 1);

namespace Civi\Banking\Api4\Action\BankTransaction;

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\BankTransactionBatch;
use Civi\Api4\OptionValue;
use Civi\Banking\AbstractBankingHeadlessTestCase;
use Civi\Banking\Permissions\Permissions;

/**
 * @covers \Civi\Banking\Api4\Action\BankTransaction\GetAction
 *
 * @group headless
 */
final class GetActionTest extends AbstractBankingHeadlessTestCase {

  protected function setUp(): void {
    parent::setUp();

    OptionValue::create(FALSE)
      ->setValues([
        'value' => 'domain1',
        'name' => 'domain1',
        'label' => 'Domain1',
        'option_group_id:name' => 'banking_transaction_domain',
      ])->execute();

    OptionValue::create(FALSE)
      ->setValues([
        'value' => 'domain2',
        'name' => 'domain2',
        'label' => 'Domain2',
        'option_group_id:name' => 'banking_transaction_domain',
      ])->execute();

    BankTransactionBatch::create(FALSE)
      ->setValues([
        'issue_date' => '2024-08-13',
        'reference' => 'ref0',
        'sequence' => 1,
        'domain' => NULL,
        'tx_count' => 0,
      ])->execute();

    $x = BankTransactionBatch::create(FALSE)
      ->setValues([
        'issue_date' => '2024-08-13',
        'reference' => 'ref1',
        'sequence' => 1,
        'domain' => 'domain1',
        'tx_count' => 0,
      ])->execute();

    $y = BankTransactionBatch::create(FALSE)
      ->setValues([
        'issue_date' => '2024-08-13',
        'reference' => 'ref2',
        'sequence' => 1,
        'domain' => 'domain2',
        'tx_count' => 0,
      ])->execute();
  }

  public function testGet(): void {
    $this->setUserPermissions(['access CiviCRM', Permissions::ACCESS_TRANSACTIONS_ALL]);
    static::assertEquals([NULL, 'domain1', 'domain2'], BankTransactionBatch::get()->execute()->column('domain'));

    $this->setUserPermissions(['access CiviCRM', Permissions::ACCESS_TRANSACTIONS]);
    static::assertEquals([NULL], BankTransactionBatch::get()->execute()->column('domain'));

    $this->setUserPermissions(['access CiviCRM', 'access CiviContribute']);
    static::assertEquals([NULL], BankTransactionBatch::get()->execute()->column('domain'));

    $this->setUserPermissions(['access CiviCRM', 'access banking transactions for domain1']);
    static::assertEquals([NULL, 'domain1'], BankTransactionBatch::get()->execute()->column('domain'));

    $this->setUserPermissions([
      'access CiviCRM',
      'access banking transactions for domain1',
      'access banking transactions for domain2',
    ]);
    static::assertEquals([NULL, 'domain1', 'domain2'], BankTransactionBatch::get()->execute()->column('domain'));

    $this->setUserPermissions(['access CiviCRM']);
    static::assertEquals([NULL, 'domain1', 'domain2'], BankTransactionBatch::get(FALSE)->execute()->column('domain'));

    static::expectException(UnauthorizedException::class);
    BankTransactionBatch::get()->execute();
  }

}
