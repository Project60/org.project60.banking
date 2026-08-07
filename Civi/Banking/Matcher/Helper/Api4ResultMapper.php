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
use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Api4ResultMapper {

  public function __construct(
    private readonly BankingExpressionLanguage $expressionLanguage
  ) {}

  /**
   * @phpstan-param array<string, string> $resultMap
   *   Mapping of target field to an APIv4 field name or an expression starting with "@=".
   *
   * @return iterable<string, mixed>
   *
   * @throws \CRM_Core_Exception
   */
  public function applyResultMap(Result $result, array $resultMap, Api4ResultMapOptions $options): iterable {
    if (0 === $result->countFetched() && $options->skipEmptyResult) {
      return;
    }

    if (NULL !== $options->indexBy) {
      $result->indexBy($options->indexBy);
    }

    foreach ($resultMap as $to => $from) {
      yield $to => $this->getValue($result, $options, $from);
    }
  }

  private function getValue(Result $result, Api4ResultMapOptions $options, string $fieldNameOrExpression): mixed {
    if (str_starts_with($fieldNameOrExpression, '@=')) {
      return $this->getValueForExpression($result, substr($fieldNameOrExpression, 2));
    }

    return $this->getValueForFieldName($result, $options, $fieldNameOrExpression);
  }

  private function getValueForExpression(Result $result, string $expression): mixed {
    return $this->expressionLanguage->evaluate($expression, ['result' => $result]);
  }

  private function getValueForFieldName(Result $result, Api4ResultMapOptions $options, string $fieldName): mixed {
    return $options->useAllResults ? $result->column($fieldName) : ($result->first()[$fieldName] ?? NULL);
  }

}
