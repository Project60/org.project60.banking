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

use Civi\Banking\ExpressionLanguage\BankingExpressionFunctionProviderInterface;
use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use Civi\Core\ClassScanner;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ExpressionLanguagePass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $providerServices = [];
    foreach (ClassScanner::get(['interface' => BankingExpressionFunctionProviderInterface::class]) as $class) {
      if (!$container->has($class)) {
        $container->autowire($class, $class);
      }

      $providerServices[] = new Reference($class);
    }

    $container->register(BankingExpressionLanguage::class, BankingExpressionLanguage::class)
      ->setArguments([NULL, $providerServices]);
  }

}
