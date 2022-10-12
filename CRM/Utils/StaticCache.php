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

/**
 * simple static cache implementation : STATIC VARIABLE
 */
static $_CRM_Utils_StaticCache = NULL;

/**
 * simple static cache implementation : ACCESS METHODS
 */
class CRM_Utils_StaticCache {

  /**
   * Will check if the given key is set in the cache
   *
   * @todo use CiviCRM caching
   *
   * @return mixed the previously stored value, or NULL
   */
  public static function getCachedEntry($key) {
    // error_log("LOOKING FOR '$key'");
    global $_CRM_Utils_StaticCache;
    if ($_CRM_Utils_StaticCache !== NULL) {
      if (isset($_CRM_Utils_StaticCache[$key])) {
        // error_log("CACHE HIT '$key'");
        return $_CRM_Utils_StaticCache[$key];
      }
    }      
    // error_log("CACHE MISS '$key'");
    return NULL;      
  }

  /**
   * Set the given cache value
   *
   * @todo use CiviCRM caching
   *
   */
  public static function setCachedEntry($key, $value) {
    global $_CRM_Utils_StaticCache;
    if ($_CRM_Utils_StaticCache === NULL) {
      // error_log("CACHE INITIALIZED");
      $_CRM_Utils_StaticCache = array();
    }
    // error_log("SETTING '$key'");
    $_CRM_Utils_StaticCache[$key] = $value;
  }
}
