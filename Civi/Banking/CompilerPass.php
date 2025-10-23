<?php

declare(strict_types = 1);

namespace Civi\Banking;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use CRM_Banking_ExtensionUtil as E;

/**
 * Compiler Class for action provider
 */
class CompilerPass implements CompilerPassInterface {

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
