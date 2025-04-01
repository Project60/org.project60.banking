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

use Psr\SimpleCache\CacheInterface;

class AssignedTransactionDomainsLoader {

  private CacheInterface $cache;

  private TransactionDomainPermissionsGenerator $permissionsGenerator;

  public function __construct(CacheInterface $cache, TransactionDomainPermissionsGenerator $permissionsGenerator) {
    $this->cache = $cache;
    $this->permissionsGenerator = $permissionsGenerator;
  }

  /**
   * @param int|null $contactId
   *   NULL for the current user's contact ID.
   *
   * @phpstan-return list<string>
   *
   * @throws \CRM_Core_Exception
   */
  public function getAssignedTransactionDomains(?int $contactId = NULL): array {
    return array_keys($this->getAssignedTransactionDomainsWithLabel($contactId));
  }

  /**
   * @param int|null $contactId
   *   NULL for the current user's contact ID.
   *
   * @phpstan-return array<string, string>
   *   Mapping of domain to label.
   *
   * @throws \CRM_Core_Exception
   */
  public function getAssignedTransactionDomainsWithLabel(?int $contactId = NULL): array {
    $contactId ??= \CRM_Core_Session::getLoggedInContactID();
    $cacheKey = 'banking.transaction.assignedDomains:' . $contactId;
    if (!$this->cache->has($cacheKey)) {
      $this->cache->set($cacheKey, $this->doGetAssignedTransactionDomainsWithLabel($contactId));
    }

    // @phpstan-ignore return.type
    return $this->cache->get($cacheKey);
  }

  /**
   * @phpstan-return array<string, string>
   *   Mapping of domain to label.
   *
   * @throws \CRM_Core_Exception
   */
  private function doGetAssignedTransactionDomainsWithLabel(?int $contactId): array {
    $domains = [];

    foreach ($this->permissionsGenerator->generatePermissions() as $permission => $details) {
      if (\CRM_Core_Permission::check($permission, $contactId)) {
        $domains[$details['_domain']] = $details['_domain_label'];
      }
    }

    return $domains;
  }

}
