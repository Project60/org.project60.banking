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

namespace Civi\Banking\Api4\Action\BankTransaction;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;
use Civi\Banking\Permissions\AssignedTransactionDomainsLoader;
use Civi\Banking\Permissions\Permissions;

/**
 * Used for BankTransaction and BankTransactionBatch.
 */
final class GetAction extends DAOGetAction {

  private ?AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader;

  public function __construct(
    $entityName,
    $actionName,
    ?AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader = NULL
  ) {
    parent::__construct($entityName, $actionName);
    $this->assignedTransactionDomainsLoader = $assignedTransactionDomainsLoader;
  }

  public function _run(Result $result): void {
    $this->addPermissionConditions();

    parent::_run($result);
  }

  private function addPermissionConditions(): void {
    if (!$this->getCheckPermissions() || \CRM_Core_Permission::check(Permissions::ACCESS_TRANSACTIONS_ALL)) {
      return;
    }

    $assignedDomains = $this->getAssignedTransactionDomainsLoader()->getAssignedTransactionDomains();
    if ([] === $assignedDomains) {
      $this->addWhere('domain', 'IS NULL');
    }
    else {
      $this->addClause(
        'OR',
        ['domain', 'IN', $assignedDomains],
        ['domain', 'IS NULL'],
      );
    }
  }

  private function getAssignedTransactionDomainsLoader(): AssignedTransactionDomainsLoader {
    // @phpstan-ignore-next-line
    return $this->assignedTransactionDomainsLoader ??= \Civi::service(AssignedTransactionDomainsLoader::class);
  }

}
