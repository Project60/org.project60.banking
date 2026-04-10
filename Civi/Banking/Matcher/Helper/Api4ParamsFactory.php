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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Webmozart\Assert\Assert;

final class Api4ParamsFactory {

  public function __construct(
    private readonly ExpressionLanguage $expressionLanguage
  ) {}

  /**
   * @phpstan-param object{
   *   action: string,
   *   params?: \stdClass,
   *   result_map?: \stdClass,
   * } $actionDefinition
   *
   * @param array<string, mixed> $expressionValues
   *
   * @return array<string, mixed>
   */
  public function createParams(
    object $actionDefinition,
    array $expressionValues = []
  ): array {
    if (property_exists($actionDefinition, 'params')) {
      Assert::isInstanceOf($actionDefinition->params, \stdClass::class);
      // Convert \stdClass to array.
      /** @var array<string, mixed> $params */
      // @phpstan-ignore argument.type
      $params = json_decode(json_encode($actionDefinition->params), TRUE);
    }
    else {
      $params = [];
    }

    if ('get' === $actionDefinition->action && !isset($params['select'])) {
      if (property_exists($actionDefinition, 'result_map')) {
        $params['select'] = [];
        Assert::isInstanceOf($actionDefinition->result_map, \stdClass::class);
        $resultMap = (array) $actionDefinition->result_map;
        foreach ($resultMap as $source) {
          if (is_string($source)) {
            $fieldNameOrExpression = $source;
          }
          else {
            Assert::notNull($source->field, 'Source field name in result map is missing');
            Assert::string($source->field, 'Expected string as source field name in result map, got %s');
            $fieldNameOrExpression = $source->field;
          }

          if (str_starts_with($fieldNameOrExpression, '@=')) {
            $params['select'] = [];
            break;
          }

          $params['select'][] = $fieldNameOrExpression;
        }
      }
    }

    self::evaluateExpressions($params, $expressionValues);

    return $params;
  }

  /**
   * Recursively evaluate and replace expressions, i.e. values starting with
   * "@=".
   *
   * @param array<mixed> $array
   * @param array<int|string, mixed> $expressionValues
   */
  private function evaluateExpressions(array &$array, array $expressionValues): void {
    foreach ($array as &$value) {
      if (is_array($value)) {
        self::evaluateExpressions($value, $expressionValues);
      }
      elseif (is_string($value) && str_starts_with($value, '@=')) {
        $expression = substr($value, 2);
        $value = $this->expressionLanguage->evaluate($expression, $expressionValues);
      }
    }
  }

}
