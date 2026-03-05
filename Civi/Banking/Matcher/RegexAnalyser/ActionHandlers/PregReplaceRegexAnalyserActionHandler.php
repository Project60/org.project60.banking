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
 * perform preg_replace.
 */
final class PregReplaceRegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'preg_replace';

  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    if (
      !is_string($action->search_pattern ?? NULL)
      || '' === ($action->search_pattern)
      || !is_string($action->replace ?? NULL)
    ) {
      $matchContext->logMessage(sprintf('Bad "%s" spec', self::NAME));

      return;
    }

    $subject = $matchContext->getValue($action->from);
    if (!is_scalar($subject) && NULL !== $subject) {
      $matchContext->logMessage(sprintf('The value of "%s" for action "%s" must be a string or null', $action->from, self::NAME));
    }
    else {
      $matchContext->setParsedValue(
        $action->to,
        preg_replace($action->search_pattern, $action->replace, (string) $subject)
      );
    }
  }

}
