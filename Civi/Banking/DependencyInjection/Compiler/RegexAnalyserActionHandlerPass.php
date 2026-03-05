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

namespace Civi\Banking\DependencyInjection\Compiler;

use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerCollector;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface;
use Civi\Core\ClassScanner;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegexAnalyserActionHandlerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $services = [];

    foreach (ClassScanner::get(['interface' => RegexAnalyserActionHandlerInterface::class]) as $class) {
      if (RegexAnalyserActionHandlerCollector::class === $class) {
        continue;
      }

      $constantName = $class . '::NAME';
      if (!defined($constantName)) {
        throw new \RuntimeException(sprintf('Constant "NAME" is missing in class "%s"', $class));
      }

      /** @var string $actionName */
      $actionName = constant($constantName);
      if (isset($services[$actionName])) {
        throw new \RuntimeException(
          sprintf(
            'Duplicate action handler with action name "%s" (%s, %s)',
            $actionName,
            (string) $services[$actionName],
            $class,
          )
        );
      }

      if (!$container->has($class)) {
        $container->autowire($class, $class);
      }

      $services[$actionName] = new Reference($class);
    }

    $container->register(RegexAnalyserActionHandlerInterface::class, RegexAnalyserActionHandlerCollector::class)
      ->addArgument(ServiceLocatorTagPass::register($container, $services))
      ->setPublic(TRUE);
  }

}
