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

namespace Civi\Banking\Matcher\CustomAction\ActionHandlers;

use Civi\Banking\Matcher\CustomAction\CustomActionContext;
use Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface;

/**
 * @implements CustomActionHandlerInterface<object>
 */
final class TestCustomActionHandler implements CustomActionHandlerInterface {

  /**
   * @var \Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface<object>
   */
  private CustomActionHandlerInterface $decorated;

  /**
   * @var list<array{\stdClass, CustomActionContext}>
   */
  private static array $testCalls = [];

  /**
   * @return list<array{\stdClass, CustomActionContext}>
   */
  public static function getTestCalls(): array {
    return self::$testCalls;
  }

  /**
   * @param \Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface<object> $decorated
   */
  public function __construct(CustomActionHandlerInterface $decorated) {
    $this->decorated = $decorated;
  }

  public function execute(\stdClass $action, CustomActionContext $context): void {
    if ('test' === $action->type) {
      self::$testCalls[] = [$action, $context];
    }
    else {
      $this->decorated->execute($action, $context);
    }
  }

}
