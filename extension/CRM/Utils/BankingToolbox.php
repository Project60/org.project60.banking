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


/**
 * random collection of tools
 */
class CRM_Utils_BankingToolbox {

  /**
   * Simple function to check if $string starts with $prefix
   *
   * @param $string  string
   * @param $prefix  string
   * @return bool
   */
  public function startsWith($string, $prefix)
  {
    return substr($string, 0, strlen($prefix)) === $prefix;
  }

  /**
   * @param $datetime  string start datetime, strtotime() compatible
   * @param $offset    string step size, strtotime() compatible
   * @param $skip      array list of skipped values, see ::skipDateTime()
   * @param $format    string output format, date()/strtotime() compatible
   * @return string aligned datetime
   */
  public static function alignDateTime($datetime, $offset, $skip, $format = "Y-m-d") {
    $datetime = date($format, strtotime($datetime));

    // make sure this is an array
    if (!is_array($skip) && !empty($skip)) {
      $skip = [$skip];
    }

    // if there is no proper skip params, don't do anything
    if (empty($skip) || !is_array($skip)) {
      return $datetime;
    }

    // loop while skipping
    while (self::skipDateTime($datetime, $skip)) {
      // this datetime should be skipped -> move on
      $datetime = date($format, strtotime($offset, strtotime($datetime)));
    }
    return $datetime;
  }

  /**
   * Check whether the
   *
   * @param $skip      array  list of skipped values. can be:
   *                               'weekend' - skip when weekend
   *                               otherwise: skip if regex matches
   * @param $datetime  string formatted datetime
   * @return boolean should the datetime be skipped?
   */
  protected static function skipDateTime($datetime, &$skip) {
    foreach ($skip as $index => $skip_value) {
      switch ($skip_value) {
        case 'skip_one':  // skip one time (can appear multiple times
          unset($skip[$index]);
          return TRUE;

        case 'weekend': // skip upon weekend day
          $day_of_week = date('N', strtotime($datetime));
          if ($day_of_week > 5) {
            return TRUE;
          }
          break;

        default:        // skip if regex matches
          if (preg_match($skip_value, $datetime)) {
            return TRUE;
          }
      }
    }
    return FALSE;
  }
}
