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

use Civi\Api4\BankTransaction;

final class TransactionAccessChecker {

  /**
   * This method should only be used in cases where direct usage of the APIv4
   * actions is not possible.
   *
   * @return bool
   *   True if the transaction is accessible, or false if it doesn't exist or
   *   permission is not granted.
   *
   * @throws \CRM_Core_Exception
   */
  public static function isAccessibleById(int $transactionId): bool {
    return 1 === BankTransaction::get()
      ->addSelect('id')
      ->addWhere('id', '=', $transactionId)
      ->execute()
      ->countMatched();
  }

}
