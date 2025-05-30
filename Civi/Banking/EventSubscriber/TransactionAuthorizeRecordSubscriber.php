<?php

/*
 * Copyright (C) 2024 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Banking\EventSubscriber;

use Civi\Api4\BankTransaction;
use Civi\Api4\BankTransactionBatch;
use Civi\Api4\Event\AuthorizeRecordEvent;
use Civi\Banking\Permissions\AssignedTransactionDomainsLoader;
use Civi\Banking\Permissions\Permissions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TransactionAuthorizeRecordSubscriber implements EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array {
    return [
      'civi.api4.authorizeRecord' => 'onAuthorizeRecord',
    ];
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function onAuthorizeRecord(AuthorizeRecordEvent $event): void {
    if (BankTransaction::getEntityName() !== $event->getEntityName()
      && BankTransactionBatch::getEntityName() !== $event->getEntityName()
    ) {
      return;
    }

    if (\CRM_Core_Permission::check(Permissions::ACCESS_TRANSACTIONS_ALL)) {
      return;
    }

    if (!$this->isDomainAllowed($event->getRecord()['domain'] ?? NULL)
      // Actually this check is only necessary if the id was given in the initial request.
      // Otherwise, the get action was already called to fetch the id.
      || (isset($event->getRecord()['id'])
        && !$this->isCurrentRecordAccessible($event->getEntityName(), $event->getRecord()['id']))
    ) {
      $event->setAuthorized(FALSE);
      $event->stopPropagation();
    }
  }

  private function isDomainAllowed(?string $domain): bool {
    if (NULL === $domain) {
      return TRUE;
    }

    /** @var \Civi\Banking\Permissions\AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader */
    $assignedTransactionDomainsLoader = \Civi::service(AssignedTransactionDomainsLoader::class);
    $assignedDomains = $assignedTransactionDomainsLoader->getAssignedTransactionDomains();

    return !in_array($domain, $assignedDomains, TRUE);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function isCurrentRecordAccessible(string $entityName, int $id): bool {
    return NULL !== civicrm_api4($entityName, 'get', [
      'select' => ['id'],
      'where' => [['id', '=', $id]],
    ])->first();
  }

}
