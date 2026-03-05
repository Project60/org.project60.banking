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

namespace Civi\Banking\DependencyInjection\Util;

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @codeCoverageIgnore
 *
 * @phpstan-type optionsT array{
 *   lazy?: bool|'auto',
 *   shared?: bool,
 *   public?: bool,
 * }
 * If lazy is 'auto', a service is lazy if the class is non-final. This
 * might be used for event subscribers. Event subscriber services are
 * created every time (even when not used), so in general it makes sense to
 * make those services lazy, unless they do not depend on any other service
 * or only on services that are created anyway or are cheap to create.
 */
final class ServiceRegistrator {

  /**
   * Autowires all PSR conform classes below the given directory (recursively).
   *
   * @phpstan-param array<string, array<string, scalar>> $tags
   *   Tag names mapped to attributes.
   * @phpstan-param optionsT $options
   *
   * @phpstan-return array<string, \Symfony\Component\DependencyInjection\Definition>
   *   Service ID mapped to definition.
   */
  public static function autowireAll(
    ContainerBuilder $container,
    string $dir,
    string $namespace,
    array $tags = [],
    array $options = []
  ): array {
    return self::doAutowireAll($container, $dir, $namespace, NULL, $tags, $options);
  }

  /**
   * Autowires all implementations of the given class or interface.
   *
   * All PSR conform classes below the given directory (recursively) are
   * considered.
   *
   * @phpstan-param class-string $classOrInterface
   * @phpstan-param array<string, array<string, scalar>> $tags
   *   Tag names mapped to attributes.
   * @phpstan-param optionsT $options
   *
   * @phpstan-return array<string, \Symfony\Component\DependencyInjection\Definition>
   *   Service ID mapped to definition.
   */
  public static function autowireAllImplementing(
    ContainerBuilder $container,
    string $dir,
    string $namespace,
    string $classOrInterface,
    array $tags = [],
    array $options = []
  ): array {
    return self::doAutowireAll($container, $dir, $namespace, $classOrInterface, $tags, $options);
  }

  /**
   * Autowires all PSR conform classes below the given directory (recursively).
   *
   * If $classOrInterface is given only those classes are autowrired that
   * implement the class/interface.
   *
   * @phpstan-param class-string|null $classOrInterface
   * @phpstan-param array<string, array<string, scalar>> $tags
   *   Tag names mapped to attributes.
   * @phpstan-param optionsT $options
   *
   * @phpstan-return array<string, \Symfony\Component\DependencyInjection\Definition>
   *   Service ID mapped to definition.
   */
  private static function doAutowireAll(
    ContainerBuilder $container,
    string $dir,
    string $namespace,
    ?string $classOrInterface,
    array $tags,
    array $options
  ): array {
    $container->addResource(new GlobResource($dir, '/*.php', TRUE));

    $definitions = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    while ($it->valid()) {
      if ($it->isFile() && 'php' === $it->getFileInfo()->getExtension()) {
        // @phpstan-ignore-next-line
        $class = static::getClass($namespace, $it->getInnerIterator());
        if (static::isServiceClass($class, $classOrInterface)) {
          /** @phpstan-var class-string $class */
          // Use existing definition, if any, so previous tags aren't lost.
          $definition = $container->hasDefinition($class)
            ? $container->findDefinition($class)
            : $container->autowire($class);

          $definition->setLazy(self::isServiceLazy($class, $options));
          $definition->setShared($options['shared'] ?? TRUE);
          $definition->setPublic($options['public'] ?? FALSE);
          foreach ($tags as $tagName => $tagAttributes) {
            $existingTagAttributesList = $definition->getTag($tagName);
            if (!in_array($tagAttributes, $existingTagAttributesList, TRUE)) {
              $definition->addTag($tagName, $tagAttributes);
            }
          }

          $definitions[$class] = $definition;
        }
      }

      $it->next();
    }

    return $definitions;
  }

  private static function getClass(string $namespace, \RecursiveDirectoryIterator $it): string {
    $class = $namespace . '\\';
    if ('' !== $it->getSubPath()) {
      $class .= str_replace('/', '\\', $it->getSubPath()) . '\\';
    }

    return $class . $it->getFileInfo()->getBasename('.php');
  }

  /**
   * @phpstan-param class-string|NULL $classOrInterface
   */
  private static function isServiceClass(string $class, ?string $classOrInterface): bool {
    if (!class_exists($class)) {
      return FALSE;
    }

    $reflClass = new \ReflectionClass($class);

    return (NULL === $classOrInterface || $reflClass->isSubclassOf($classOrInterface))
      && !$reflClass->isAbstract();
  }

  /**
   * @phpstan-param class-string $class
   * @phpstan-param optionsT $options
   */
  private static function isServiceLazy(string $class, array $options): bool {
    if (!isset($options['lazy'])) {
      return FALSE;
    }

    if ('auto' === $options['lazy']) {
      return !(new \ReflectionClass($class))->isFinal();
    }

    return $options['lazy'];
  }

}
