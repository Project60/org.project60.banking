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

namespace Civi\Banking\Permissions;

final class AllowedDomainsSqlGenerator {

  private AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader;

  public function __construct(AssignedTransactionDomainsLoader $assignedTransactionDomainsLoader) {
    $this->assignedTransactionDomainsLoader = $assignedTransactionDomainsLoader;
  }

  public function generateWhereClause(string $tableAlias = ''): string {
    $fieldName = 'domain';
    if ('' !== $tableAlias) {
      $fieldName = $tableAlias . '.' . $fieldName;
    }

    $assignedDomains = $this->assignedTransactionDomainsLoader->getAssignedTransactionDomains();
    if ([] === $assignedDomains) {
      return $fieldName . ' IS NULL';
    }
    else {
      $quotedDomains = array_map([\CRM_Core_DAO::class, 'escapeString'], $assignedDomains);

      return "($fieldName is NULL OR $fieldName IN ('" . implode("','", $quotedDomains) . "'))";
    }
  }

}
