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

namespace Civi\Banking\Api4\Traits;

use Civi\Banking\Permissions\Permissions;

/**
 * Permissions for funding entities related to administration.
 */
trait TransactionPermissionsTrait {

  /**
   * @phpstan-return array<string, array<string|string[]>>
   */
  public static function permissions(): array {
    return [
      'meta' => [
        'access CiviCRM',
        [
          Permissions::ACCESS_TRANSACTIONS,
          'access CiviContribute',
        ],
      ],
      'default' => [
        'access CiviCRM',
        [
          Permissions::ACCESS_TRANSACTIONS,
          'access CiviContribute',
        ],
      ],
      'delete' => ['administer CiviCRM'],
    ];
  }

}
