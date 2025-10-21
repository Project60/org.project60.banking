<?php
declare(strict_types = 1);

namespace Banking;

use Composer\Package\Link;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;

final class ComposerHelper {

  /**
   * Prevents CiviCRM extensions from being installed as dependency.
   */
  public static function preUpdate(Event $event): void {
    $repositoryManager = $event->getComposer()->getRepositoryManager();
    $package = $event->getComposer()->getPackage();
    $package->setRequires(
      array_filter(
        $package->getRequires(),
        fn (Link $require, string $name) =>
          'civicrm/civicrm-core' !== $name &&
          'civicrm/civicrm-packages' !== $name &&
          'civicrm-ext' !== $repositoryManager->findPackage($name, $require->getConstraint())?->getType(),
        ARRAY_FILTER_USE_BOTH
      )
    );
  }

}
