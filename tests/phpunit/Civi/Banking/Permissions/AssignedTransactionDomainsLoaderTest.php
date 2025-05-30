<?php
declare(strict_types = 1);

namespace Civi\Banking\Permissions;

use Civi\Banking\AbstractBankingHeadlessTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Civi\Banking\Permissions\AssignedTransactionDomainsLoader
 *
 * @group headless
 */
final class AssignedTransactionDomainsLoaderTest extends AbstractBankingHeadlessTestCase {

  private AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader;

  /**
   * @var \Civi\Banking\Permissions\TransactionDomainPermissionsGenerator&\PHPUnit\Framework\MockObject\MockObject
   */
  private MockObject $permissionsGeneratorMock;

  protected function setUp(): void {
    parent::setUp();
    $this->permissionsGeneratorMock = $this->createMock(TransactionDomainPermissionsGenerator::class);
    $this->assignedTransactionDomainsLoader = new AssignedTransactionDomainsLoader(
      new \Sabre\Cache\Memory(),
      $this->permissionsGeneratorMock
    );
  }

  public function testGetAssignedTransactionDomains(): void {
    $this->permissionsGeneratorMock->method('generatePermissions')
      ->willReturn([
        'banking transaction test1' => [
          'label' => 'Label1',
          'description' => 'Description1',
          '_domain' => 'test1',
          '_domain_label' => 'Test1',
        ],
        'banking transaction test2' => [
          'label' => 'Label2',
          'description' => 'Description2',
          '_domain' => 'test2',
          '_domain_label' => 'Test2',
        ],
      ]);

    $this->setUserPermissions(['banking transaction test1']);

    static::assertEquals(['test1'], $this->assignedTransactionDomainsLoader->getAssignedTransactionDomains());
  }

  public function testGetAssignedTransactionDomainsWithLabel(): void {
    $this->permissionsGeneratorMock->method('generatePermissions')
      ->willReturn([
        'banking transaction test1' => [
          'label' => 'Label1',
          'description' => 'Description1',
          '_domain' => 'test1',
          '_domain_label' => 'Test1',
        ],
        'banking transaction test2' => [
          'label' => 'Label2',
          'description' => 'Description2',
          '_domain' => 'test2',
          '_domain_label' => 'Test2',
        ],
      ]);

    $this->setUserPermissions(['banking transaction test2']);

    static::assertEquals(
      ['test2' => 'Test2'],
      $this->assignedTransactionDomainsLoader->getAssignedTransactionDomainsWithLabel()
    );
  }

}
