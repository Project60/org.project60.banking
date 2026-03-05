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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

final class RegexAnalyserActionHandlerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $services = [];

    foreach ($container->findTaggedServiceIds(RegexAnalyserActionHandlerInterface::class) as $id => $tags) {
      foreach ($tags as $attributes) {
        $actionName = $this->getActionName($container, $id, $attributes);
        if (isset($services[$actionName])) {
          throw new RuntimeException(
            sprintf(
              'Duplicate service with tag "%s" and action name "%s" (IDs: %s, %s)',
              RegexAnalyserActionHandlerInterface::class,
              $actionName,
              (string) $services[$actionName],
              $id,
            )
          );
        }

        $services[$actionName] = new Reference($id);
      }
    }

    $container->register(RegexAnalyserActionHandlerInterface::class, RegexAnalyserActionHandlerCollector::class)
      ->addArgument(ServiceLocatorTagPass::register($container, $services))
      ->setPublic(TRUE);
  }

  /**
   * @param array{name?: string} $attributes
   *
   * @throws \RuntimeException
   */
  private function getActionName(ContainerBuilder $container, string $id, array $attributes): string {
    if (array_key_exists('name', $attributes)) {
      return $attributes['name'];
    }

    $constantName = $this->getServiceClass($container, $id) . '::NAME';
    if (defined($constantName)) {
      // @phpstan-ignore return.type
      return constant($constantName);
    }

    throw new \RuntimeException(sprintf('Could not find action name for service "%s"', $id));
  }

  /**
   * @phpstan-return class-string
   */
  private function getServiceClass(ContainerBuilder $container, string $id): string {
    $definition = $container->getDefinition($id);

    /** @phpstan-var class-string $class */
    $class = $definition->getClass() ?? $id;

    return $class;
  }

}
