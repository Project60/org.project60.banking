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

namespace Civi\Banking\Matcher\RegexAnalyser;

use Psr\Container\ContainerInterface;

final class RegexAnalyserActionHandlerCollector implements RegexAnalyserActionHandlerInterface {

  public function __construct(
    private readonly ContainerInterface $container,
  ) {}

  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    [$actionName] = explode(':', $action->action, 2);
    if (!$this->container->has($actionName)) {
      throw new \InvalidArgumentException(sprintf('Unknown action "%s"', $actionName));
    }

    $this->getAction($actionName)->execute($action, $matchContext);
  }

  private function getAction(string $actionName): RegexAnalyserActionHandlerInterface {
    if (!$this->container->has($actionName)) {
      throw new \InvalidArgumentException(sprintf('Unknown action "%s"', $actionName));
    }

    // @phpstan-ignore return.type
    return $this->container->get($actionName);
  }

}
