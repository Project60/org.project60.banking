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

use Civi\Banking\Matcher\CustomAction\CustomActionHandlerCollector;
use Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface;
use Civi\Core\ClassScanner;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class CustomActionHandlerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $services = [];

    foreach (ClassScanner::get(['interface' => CustomActionHandlerInterface::class]) as $class) {
      if (CustomActionHandlerCollector::class === $class) {
        continue;
      }

      $constantName = $class . '::TYPE';
      if (!defined($constantName)) {
        throw new \RuntimeException(sprintf('Constant "TYPE" is missing in class "%s"', $class));
      }

      /** @var string $type */
      $type = constant($constantName);
      if (isset($services[$type])) {
        throw new \RuntimeException(
          sprintf(
            'Duplicate action handler with action type "%s" (%s, %s)',
            $type,
            (string) $services[$type],
            $class,
          )
        );
      }

      if (!$container->has($class)) {
        $container->autowire($class, $class);
      }

      $services[$type] = new Reference($class);
    }

    $container->register(CustomActionHandlerInterface::class, CustomActionHandlerCollector::class)
      ->addArgument(ServiceLocatorTagPass::register($container, $services))
      ->setPublic(TRUE);
  }

}
