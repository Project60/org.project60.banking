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

use Civi\Api4\OptionValue;
use CRM_Banking_ExtensionUtil as E;
use Psr\SimpleCache\CacheInterface;

class TransactionDomainPermissionsGenerator {

  private CacheInterface $cache;

  public function __construct(CacheInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * @phpstan-return array<string, array{
   *   label: string,
   *   description: string,
   *   implies: list{Permissions::ACCESS_TRANSACTIONS},
   *   _domain: string,
   *   _domain_label: string,
   * }>
   *
   * @throws \CRM_Core_Exception
   */
  public function generatePermissions(): array {
    if (!$this->cache->has('banking.transaction.permissions')) {
      $this->cache->set('banking.transaction.permissions', iterator_to_array($this->doGeneratePermissions()));
    }

    // @phpstan-ignore return.type
    return $this->cache->get('banking.transaction.permissions');
  }

  /**
   * @phpstan-return \Traversable<string, array{
   *   label: string,
   *   description: string,
   *   implies: list{Permissions::ACCESS_TRANSACTIONS},
   *   _domain: string,
   *   _domain_label: string,
   * }>
   *
   * @throws \CRM_Core_Exception
   */
  private function doGeneratePermissions(): \Traversable {
    $domains = civicrm_api4(OptionValue::getEntityName(), 'get', [
      'select' => ['value', 'label'],
      'where' => [
        ['option_group_id:name', '=', 'banking_transaction_domain'],
        ['is_active', '=', TRUE],
      ],
      'orderBy' => ['weight' => 'ASC'],
      'checkPermissions' => FALSE,
    ]);

    /** @phpstan-var array{value: string, label: string} $domain */
    foreach ($domains as $domain) {
      yield 'access banking transactions for ' . $domain['value'] => [
        'label' => E::ts('CiviBanking: Access transactions for %1', [1 => $domain['label']]),
        'description' => E::ts('Access CiviBanking transactions with domain %1.', [1 => $domain['label']]),
        'implies' => [Permissions::ACCESS_TRANSACTIONS],
        '_domain' => $domain['value'],
        '_domain_label' => $domain['label'],
      ];
    }
  }

}
