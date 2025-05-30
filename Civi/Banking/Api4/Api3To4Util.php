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

namespace Civi\Banking\Api4;

final class Api3To4Util {

  /**
   * @phpstan-param array<string, mixed> $params
   * @phpstan-return list<array{string, string, mixed}>
   *
   * @throws \CRM_Core_Exception
   */
  public static function createWhere(string $entityName, array $params): array {
    $where = [];

    $fieldNames = civicrm_api4($entityName, 'getFields', [
      'checkPermissions' => FALSE,
      'select' => ['name'],
    ])->column('name');

    foreach ($params as $fieldName => $value) {
      if (!in_array($fieldName, $fieldNames, TRUE)) {
        continue;
      }

      if (is_array($value)) {
        $where[] = [$fieldName, 'IN', $value];
      }
      elseif (NULL === $value) {
        $where[] = [$fieldName, 'IS NULL'];
      }
      else {
        $where[] = [$fieldName, '=', $value];
      }
    }

    return $where;
  }

  /**
   * @param array<string, mixed> $params
   *
   * @phpstan-return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   */
  public static function createValues(string $entityName, array $params): array {
    $fieldNames = civicrm_api4($entityName, 'getFields', [
      'checkPermissions' => FALSE,
      'select' => ['name'],
    ])->column('name');

    return array_filter($params, fn ($key) => in_array($key, $fieldNames, TRUE), ARRAY_FILTER_USE_KEY);
  }

}
