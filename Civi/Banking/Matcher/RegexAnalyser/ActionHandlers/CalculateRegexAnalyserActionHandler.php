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

namespace Civi\Banking\Matcher\RegexAnalyser\ActionHandlers;

use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext;
use Webmozart\Assert\Assert;

/**
 * CALCULATE the new value with an php expression, using {}-based tokens.
 */
final class CalculateRegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'calculate';

  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    $expression = $action->from;
    Assert::string($expression, sprintf('"from" must be a string for the %s action', self::NAME));

    $matches = [];
    while (preg_match('#(?P<variable>{[^}]+})#', $expression, $matches)) {
      // replace variable with value
      $token = trim($matches[0], '{}');
      $value = $matchContext->getValue($token);
      if (!is_scalar($value) && NULL !== $value) {
        $matchContext->logMessage(
          sprintf('The value of "%s" for action "%s" must be a scalar or null', $token, self::NAME)
        );

        return;
      }

      $expression = preg_replace('#(?P<variable>{[^}]+})#', (string) $value, $expression, 1);
      if (!is_string($expression)) {
        $matchContext->logMessage(sprintf('Invalid expression "%s" for action "%s"', $expression, self::NAME));

        return;
      }
    }
    // phpcs:disable Drupal.Functions.DiscouragedFunctions.Discouraged
    $matchContext->setParsedValue($action->to, eval("return $expression;"));
    // phpcs:enable
  }

}
