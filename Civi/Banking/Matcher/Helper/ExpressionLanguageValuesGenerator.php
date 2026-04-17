<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Banking\Matcher\Helper;

use Webmozart\Assert\Assert;

final class ExpressionLanguageValuesGenerator {

  /**
   * Generates a values array for use in Symfony Expression Language to access
   * values in expressions via e.g. "btx.amount" or "btx['my.value']". (For keys
   * containing dots or special characters, array access has to be used.)
   *
   * @param list<string> $prefixes
   *   List of prefixes, e.g. "btx" or "ba".
   * @param callable(string): mixed $getValueCallback
   *
   * @return array<string, object>
   */
  public static function generateValuesForPrefixes(array $prefixes, callable $getValueCallback): array {
    $values = [];
    foreach ($prefixes as $prefix) {
      $values[$prefix] = self::createValueWrapper($prefix, $getValueCallback);
    }

    return $values;
  }

  /**
   * @param callable(string): mixed $getValueCallback
   */
  private static function createValueWrapper(string $prefix, callable $getValueCallback): object {
    return new class ($prefix, $getValueCallback(...)) implements \ArrayAccess {

      /**
       * @param \Closure(string): mixed $getValueCallback
       */
      public function __construct(
        private readonly string $prefix,
        private readonly \Closure $getValueCallback,
      ) {}

      public function __get(string $name): mixed {
        return ($this->getValueCallback)($this->prefix . '.' . $name);
      }

      public function offsetExists(mixed $offset): bool {
        Assert::string($offset);
        return NULL !== ($this->getValueCallback)($this->prefix . '.' . $offset);
      }

      public function offsetGet(mixed $offset): mixed {
        Assert::string($offset);

        return ($this->getValueCallback)($this->prefix . '.' . $offset);
      }

      public function offsetSet(mixed $offset, mixed $value): void {
        throw new \BadMethodCallException('Unsupported method');
      }

      public function offsetUnset(mixed $offset): void {
        throw new \BadMethodCallException('Unsupported method');
      }

    };
  }

}
