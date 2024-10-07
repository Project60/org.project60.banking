<?php
declare(strict_types = 1);

namespace Civi\Banking\Permissions;

use Civi\Api4\Generic\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Banking\Permissions\TransactionDomainPermissionsGenerator
 *
 * @runTestsInSeparateProcesses to mock civicrm_api4()
 */
final class TransactionDomainPermissionsGeneratorTest extends TestCase {

  use \phpmock\phpunit\PHPMock;

  private TransactionDomainPermissionsGenerator $permissionsGenerator;

  protected function setUp(): void {
    parent::setUp();
    $this->permissionsGenerator = new TransactionDomainPermissionsGenerator(new \Sabre\Cache\Memory());
  }

  public function testGeneratePermissions(): void {
    $civicrmApi4Mock = $this->getFunctionMock(__NAMESPACE__, 'civicrm_api4');

    $civicrmApi4Mock->expects(static::once())->with('OptionValue', 'get', [
      'select' => ['value', 'label'],
      'where' => [['option_group_id:name', '=', 'banking_transaction_domain']],
      'checkPermissions' => FALSE,
    ])->willReturn(new Result([['value' => 'test', 'label' => 'Test']]));

    static::assertEquals([
      'access banking transactions for test' => [
        'label' => 'CiviBanking: Access transactions for Test',
        'description' => 'Access CiviBanking transactions with domain Test.',
        'implies' => [Permissions::ACCESS_TRANSACTIONS],
        '_domain' => 'test',
        '_domain_label' => 'Test',
      ],
    ], $this->permissionsGenerator->generatePermissions());
  }

}
