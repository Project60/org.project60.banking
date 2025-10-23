<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

/**
 * simple static cache implementation : ACCESS METHODS
 */
class CRM_Utils_StaticCache {

  /**
   * @var array<string, mixed>
   */
  private static array $cache = [];

  /**
   * Will check if the given key is set in the cache
   *
   * @todo use CiviCRM caching
   *
   * @return mixed the previously stored value, or NULL
   */
  public static function getCachedEntry(string $key): mixed {
    return self::$cache[$key] ?? NULL;
  }

  /**
   * Set the given cache value
   *
   * @todo use CiviCRM caching
   */
  public static function setCachedEntry(string $key, mixed $value): void {
    self::$cache[$key] = $value;
  }

  public static function clearCache(): void {
    self::$cache = [];
  }

}
