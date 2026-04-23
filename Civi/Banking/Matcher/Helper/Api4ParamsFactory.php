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

use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use Webmozart\Assert\Assert;

final class Api4ParamsFactory {

  public function __construct(
    private readonly BankingExpressionLanguage $expressionLanguage
  ) {}

  /**
   * @phpstan-param object{
   *   action: string,
   *   params?: \stdClass,
   *   result_map?: \stdClass,
   *   use_all_results?: bool,
   *   index_by?: string,
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

    if ('get' === $actionDefinition->action) {
      if (!isset($params['select']) && property_exists($actionDefinition, 'result_map')) {
        $params['select'] = [];
        if (property_exists($actionDefinition, 'index_by')) {
          $params['select'][] = $actionDefinition->index_by;
        }

        $resultMapHasExpression = FALSE;
        Assert::isInstanceOf($actionDefinition->result_map, \stdClass::class);
        $resultMap = (array) $actionDefinition->result_map;
        foreach ($resultMap as $fieldNameOrExpression) {
          Assert::string($fieldNameOrExpression, 'APIv4 field name or expression expected in result map, got %s');
          if (str_starts_with($fieldNameOrExpression, '@=')) {
            // Select all fields if an expression is used.
            $params['select'] = [];
            $resultMapHasExpression = TRUE;
            break;
          }

          $params['select'][] = $fieldNameOrExpression;
        }

        if (!($actionDefinition->use_all_results ?? FALSE) && !$resultMapHasExpression) {
          $params['limit'] ??= 1;
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
