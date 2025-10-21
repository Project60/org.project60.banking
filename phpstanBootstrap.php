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

declare(strict_types = 1);

// phpcs:disable Drupal.Commenting.DocComment.ContentAfterOpen
/** @var \PHPStan\DependencyInjection\Container $container */
/** @phpstan-var array<string> $bootstrapFiles */
$bootstrapFiles = $container->getParameter('bootstrapFiles');
foreach ($bootstrapFiles as $bootstrapFile) {
  if (str_ends_with($bootstrapFile, 'vendor/autoload.php')) {
    $vendorDir = dirname($bootstrapFile);
    $civiCrmVendorDir = $vendorDir . '/civicrm';
    $civiCrmCoreDir = $civiCrmVendorDir . '/civicrm-core';
    if (file_exists($civiCrmCoreDir)) {
      set_include_path(get_include_path()
        . PATH_SEPARATOR . $civiCrmCoreDir
        . PATH_SEPARATOR . $civiCrmVendorDir . '/civicrm-packages'
      );
      // $bootstrapFile might not be included, yet. It is required for the
      // following require_once, though.
      require_once $bootstrapFile;
      // Prevent error "Class 'CRM_Core_Exception' not found in file".
      require_once $civiCrmCoreDir . '/CRM/Core/Exception.php';

      break;
    }
  }
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}
