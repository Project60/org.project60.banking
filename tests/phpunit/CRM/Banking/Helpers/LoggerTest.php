<?php
/*
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

/**
 * @covers CRM_Banking_Helpers_Logger
 *
 * @group headless
 */
final class CRM_Banking_Helpers_LoggerTest extends CRM_Banking_TestBase {

  /**
   * Without a log level setting the logger is off and raises no deprecation.
   */
  public function testLogLevelWithoutSettingIsOff(): void {
    Civi::settings()->revert('banking_log_level');

    $deprecations = [];
    set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
      $deprecations[] = $errstr;
      return TRUE;
    }, E_DEPRECATED);
    try {
      $logger = new class() extends CRM_Banking_Helpers_Logger {

        public function __construct() {
          parent::__construct();
        }

        public function getLogLevel(): mixed {
          return $this->log_level;
        }

      };
    }
    finally {
      restore_error_handler();
    }

    static::assertSame([], $deprecations);
    static::assertSame('off', $logger->getLogLevel());
  }

}
