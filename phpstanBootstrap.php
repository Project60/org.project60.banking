<?php
/*
 * Copyright (C) 2022 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
declare(strict_types = 1);

// phpcs:disable Drupal.Commenting.DocComment.ContentAfterOpen
/** @var \PHPStan\DependencyInjection\Container $container */

use Composer\Autoload\ClassLoader;

/** @phpstan-var list<string> $bootstrapFiles */
$bootstrapFiles = $container->getParameter('bootstrapFiles');
foreach ($bootstrapFiles as $bootstrapFile) {
  if (str_ends_with($bootstrapFile, 'vendor/autoload.php')) {
    $vendorDir = dirname($bootstrapFile);
    // Installation via composer (e.g. as Drupal module)
    $civiCrmVendorDir = "$vendorDir/civicrm";
    $civiCrmCoreDir = "$civiCrmVendorDir/civicrm-core";
    $civiCrmCoreExtDir = "$civiCrmVendorDir/civicrm-core/ext";
    $civiCrmPackagesDir = "$civiCrmVendorDir/civicrm-packages";
    // Installation without composer (e.g. as WordPress plugin)
    if (!is_dir($civiCrmCoreDir) || !is_dir($civiCrmPackagesDir)) {
      $civiCrmCoreDir = "$vendorDir/..";
      $civiCrmPackagesDir = "$civiCrmCoreDir/packages";
    }
    if (!is_dir($civiCrmCoreDir) || !is_dir($civiCrmPackagesDir)) {
      continue;
    }
    if (file_exists($civiCrmCoreDir)) {
      set_include_path(
        get_include_path()
        . PATH_SEPARATOR . $civiCrmCoreDir
        . PATH_SEPARATOR . $civiCrmPackagesDir
      );

      // Required for class_exists checks to work.
      require_once $bootstrapFile;

      require_once 'api/api.php';
      require_once 'api/v3/utils.php';
      require_once 'api/v3/Generic.php';
      /** @var list<string> $api3GenericFiles */
      $api3GenericFiles = glob("$civiCrmCoreDir/api/v3/Generic/*.php");
      array_map(fn ($file) => require_once $file, $api3GenericFiles);

      $loader = new ClassLoader();
      $loader->addClassMap([
        'civicrm_api3' => "$civiCrmCoreDir/api/class.api.php",
        'API_Wrapper' => "$civiCrmCoreDir/api/Wrapper.php",
      ]);
      $loader->add('CRM_', [$civiCrmCoreDir]);
      $loader->add('DB_', [$civiCrmPackagesDir]);
      $loader->add('HTML_', [$civiCrmPackagesDir]);

      // @phpstan-ignore-next-line
      if ($container->getParameter('civicrm')['implicitSmartyMethodsUsed']) {
        // In CiviCRM <=6.16 the class \Smarty extended by
        // \CRM_Core_SmartyCompatibility uses the __call() method to delegate
        // method calls to \Smarty\Smarty, but hasn't defined the methods itself
        // which results in method not found errors. By aliasing \Smarty\Smarty
        // we avoid these errors.
        $smartyAutoloadFile = "$civiCrmPackagesDir/smarty5/vendor/autoload.php";
        if (file_exists($smartyAutoloadFile)) {
          require_once $smartyAutoloadFile;
          class_alias(\Smarty\Smarty::class, \Smarty::class);
        }
        // Since CiviCRM 6.17 Smarty is installed as composer package.
        elseif (class_exists(\Smarty\Smarty::class)) {
          // Before CiviCRM 6.18 the class delegating the method calls was \Smarty.
          class_alias(\Smarty\Smarty::class, \Smarty::class);
          // Since CiviCRM 6.18 the class delegating the method calls is \Civi\Smarty.
          class_alias(\Smarty\Smarty::class, \Civi\Smarty::class);
        }
      }
      else {
        // Required in CiviCRM <=6.16
        // Prevent call to method getPath() on null in crm_smarty_compatibility_get_path()
        // https://github.com/civicrm/civicrm-core/blob/001aa785e7b6b9b4252a33cc6726e1ea2657487b/CRM/Core/SmartyCompatibility.php#L48
        // phpcs:ignore Drupal.Commenting.ClassComment.WrongStyle
        class Smarty {}
      }

      $simpleXml = simplexml_load_file(__DIR__ . '/info.xml');
      foreach ($simpleXml->requires->ext ?? [] as $extension) {
        addExtensionToClassLoader($loader, (string) $extension, $civiCrmCoreExtDir);
      }

      /** @var string $extension */
      // @phpstan-ignore-next-line
      foreach ($container->getParameter('civicrm')['optionalExtensions'] ?? [] as $extension) {
        addExtensionToClassLoader($loader, $extension, $civiCrmCoreExtDir);
      }

      $loader->register();

      break;
    }
  }
}

// Ensure that type hint HTML_QuickForm_Element is recognized, though the class name is HTML_QuickForm_element.
// See https://github.com/civicrm/civicrm-core/pull/36666
class_exists('HTML_QuickForm_element');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

function addExtensionToClassLoader(ClassLoader $loader, string $extension, string $civiCrmCoreExtDir): void {
  // Support symlinks. Current working dir should be the extensions' directory
  // relative to the "ext" directory.
  // Note: getcwd() is not used because it returns the real path.
  static $currentWorkingDirParent;
  // @phpstan-ignore argument.type
  $currentWorkingDirParent ??= dirname(getenv('PWD'));
  $candidates = [
    "$currentWorkingDirParent/$extension",
    __DIR__ . "/../$extension",
    "$civiCrmCoreExtDir/$extension",
  ];
  foreach ($candidates as $candidate) {
    if (is_dir($candidate)) {
      addExtensionDirToClassLoader($loader, $candidate);

      return;
    }
  }

  fprintf(STDERR, "Warning: Could not find CiviCRM extension $extension.\n\n");
}

function addExtensionDirToClassLoader(ClassLoader $loader, string $extensionDir): void {
  if (is_dir("$extensionDir/CRM")) {
    $loader->add('CRM_', [$extensionDir]);
  }
  if (is_dir("$extensionDir/Civi")) {
    $loader->addPsr4('Civi\\', ["$extensionDir/Civi"]);
  }

  if (file_exists("$extensionDir/vendor/autoload.php")) {
    require_once "$extensionDir/vendor/autoload.php";
  }
}
