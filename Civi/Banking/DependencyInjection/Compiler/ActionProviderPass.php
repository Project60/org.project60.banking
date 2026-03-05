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

use CRM_Banking_ExtensionUtil as E;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler Class for action provider
 */
class ActionProviderPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    if ($container->hasDefinition('action_provider')) {
      $actionProviderDefinition = $container->getDefinition('action_provider');
      $actionProviderDefinition->addMethodCall('addAction',
        ['AddIban', 'Civi\Banking\Actions\AddIban', E::ts('Add IBAN to Contact'), []]);
      $actionProviderDefinition->addMethodCall('addAction',
        ['FindLatestAccount', 'Civi\Banking\Actions\FindLatestAccount', E::ts('Find Latest Bank Account for Contact'), []]);
    }
    if ($container->hasDefinition('data_processor_factory')) {
      $factoryDefinition = $container->getDefinition('data_processor_factory');
      $factoryDefinition->addMethodCall('addOutputHandler', [
        'banking_iban',
        'Civi\Banking\DataProcessor\FieldoutputHandler\ContactIBANOutputhandler',
        E::ts('IBAN (Banking)'),
      ]);
      $factoryDefinition->addMethodCall('addDataSource', [
        'bank_account',
        'Civi\Banking\DataProcessor\Source\BankAccount',
        E::ts('CiviBanking: Bank Account'),
      ]);
    }
  }

}
