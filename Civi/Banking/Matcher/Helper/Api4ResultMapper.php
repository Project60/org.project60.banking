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

use Civi\Api4\Generic\Result;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type resultMapT array<string, string|object{
 *   field: string,
 *   filter?: 'first'|'last',
 *   value_separator?: string,
 * }>
 */
final class Api4ResultMapper {

  public function __construct(
    private readonly ExpressionLanguage $expressionLanguage
  ) {}

  /**
   * @phpstan-param resultMapT $resultMap
   *   Mapping of target field name to an APIv4 field name, an expression or an
   *   object containing the APIv4 field name.
   * @param callable(string, mixed): void $setValueCallback
   */
  public function mapResult(Result $result, array $resultMap, callable $setValueCallback): void {
    if (0 === $result->countFetched()) {
      return;
    }

    foreach ($resultMap as $to => $from) {
      if (!is_string($from) && !$from instanceof \stdClass) {
        throw new \InvalidArgumentException(sprintf('Invalid source definition for field "%s" in result map', $to));
      }

      $setValueCallback($to, $this->getValue($result, $from));
    }
  }

  private function applyFilter(mixed $value, string $filter): mixed {
    if ('first' === $filter) {
      if (is_array($value)) {
        return [] === $value ? NULL : reset($value);
      }

      return $value;
    }

    if ('last' === $filter) {
      if (is_array($value)) {
        return [] === $value ? NULL : end($value);
      }

      return $value;
    }

    throw new \InvalidArgumentException(sprintf('Unknown filter "%s"', $filter));
  }

  private function applyModifications(mixed $value, \stdClass $source): mixed {
    if (property_exists($source, 'filter')) {
      Assert::string('Filter has to be a string in source definition of result map');
      $value = $this->applyFilter($value, $source->filter);
    }

    return $value;
  }

  private function getValue(Result $result, string|\stdClass $source): mixed {
    if (is_string($source)) {
      if (str_starts_with($source, '@=')) {
        return $this->getValueForExpression($result, substr($source, 2));
      }

      return $this->getValueForFieldName($result, $source);
    }

    $fieldName = $source->field;
    Assert::notNull($fieldName, 'Source field name in result map is missing');
    Assert::string($fieldName, 'Expected string as source field name in result map, got %s');

    $valueSeparator = $source->value_separator ?? ',';
    Assert::string($valueSeparator, 'Expected string as value separator in result map, got %s');

    return $this->applyModifications(
      $this->getValueForFieldName($result, $fieldName, $valueSeparator),
      $source
    );
  }

  private function getValueForExpression(Result $result, string $expression): mixed {
    return $this->expressionLanguage->evaluate($expression, ['result' => $result]);
  }

  private function getValueForFieldName(Result $result, string $fieldName, string $valueSeparator = ','): mixed {
    return match ($result->countFetched()) {
      1 => $result->single()[$fieldName],
      default => implode($valueSeparator, $result->column($fieldName)),
    };
  }

}
