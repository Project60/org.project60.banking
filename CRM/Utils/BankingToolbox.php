<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2019 SYSTOPIA                            |
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
 * random collection of tools
 */
class CRM_Utils_BankingToolbox {

  /**
   * Simple function to check if $string starts with $prefix.
   */
  public function startsWith(string $string, string $prefix): bool {
    return substr($string, 0, strlen($prefix)) === $prefix;
  }

  /**
   * @param string $datetime start datetime, strtotime() compatible
   * @param string $offset step size, strtotime() compatible
   * @param list<string>|string $skip list of skipped values, see ::skipDateTime()
   * @param string $format output format, date()/strtotime() compatible
   *
   * @return string aligned datetime
   */
  public static function alignDateTime(
    string $datetime,
    string $offset,
    array|string $skip,
    string $format = 'Y-m-d'
  ): string {
    $time = strtotime($datetime);
    assert(is_int($time));
    $datetime = date($format, $time);

    // if there is no proper skip params, don't do anything
    if ([] === $skip || '' === $skip) {
      return $datetime;
    }

    $skip = (array) $skip;

    // loop while skipping
    while (self::skipDateTime($datetime, $skip)) {
      // this datetime should be skipped -> move on
      $offsetTime = strtotime($offset, strtotime($datetime));
      assert(is_int($offsetTime));
      $datetime = date($format, $offsetTime);
    }
    return $datetime;
  }

  /**
   * Check whether the
   *
   * @param string $datetime formatted datetime
   * @param list<string> $skip list of skipped values. can be:
   *                               'weekend' - skip when weekend
   *                               otherwise: skip if regex matches
   *
   * @return boolean should the datetime be skipped?
   */
  protected static function skipDateTime(string $datetime, array $skip): bool {
    foreach ($skip as $index => $skip_value) {
      switch ($skip_value) {
        // skip one time (can appear multiple times
        case 'skip_one':
          unset($skip[$index]);
          return TRUE;

        // skip upon weekend day
        case 'weekend':
          $day_of_week = date('N', strtotime($datetime));
          if ($day_of_week > 5) {
            return TRUE;
          }
          break;

        // skip if regex matches
        default:
          if (preg_match($skip_value, $datetime)) {
            return TRUE;
          }
      }
    }
    return FALSE;
  }

}
