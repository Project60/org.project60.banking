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

/**
 * COPY value, but remove leading zeros.
 */
final class CopyAppendRegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'copy_append';

  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    $currentValue = $matchContext->getParsedValue($action->to);
    $value = $matchContext->getValue($action->from);

    if (!is_scalar($currentValue) && NULL !== $currentValue) {
      $matchContext->logMessage(sprintf('The parsed value of "%s" for action "%s" must be a scalar or null', $action->to, self::NAME));
    }
    elseif (!is_scalar($value) && NULL !== $value) {
      $matchContext->logMessage(sprintf('The value of "%s" for action "%s" must be a scalar or null', $action->from, self::NAME));
    }
    else {
      $matchContext->setParsedValue($action->to, $currentValue . $value);
    }
  }

}
