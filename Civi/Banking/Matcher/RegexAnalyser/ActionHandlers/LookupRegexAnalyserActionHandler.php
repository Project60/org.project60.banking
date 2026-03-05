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
 * LOOK UP values via API::getsingle.
 */
final class LookupRegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'lookup';

  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    //   parameters are in format: "EntityName,result_field,lookup_field"
    $params = explode(',', substr($action->action, 7));
    $value = $matchContext->getValue($action->from) ?? '';
    $query = [$params[2] => $value, 'return' => $params[1]];
    if (is_array($action->parameters ?? NULL)) {
      $query += $action->parameters;
    }

    // execute and log
    $matchContext->setLogTimer('regex:lookup');

    $matchContext->logMessage("Calling API {$params[0]}.getsingle: " . json_encode($query), 'debug');
    /** @var array<string, mixed> $result */
    $result = civicrm_api3($params[0], 'getsingle', $query);
    $matchContext->logMessage('API result: ' . json_encode($result), 'debug');
    $matchContext->logTime("API {$params[0]}.getsingle", 'regex:lookup');

    if (!(bool) ($result['is_error'] ?? NULL)) {
      // something was found... copy value
      $matchContext->setParsedValue($action->to, $result[$params[1]]);
    }
  }

}
