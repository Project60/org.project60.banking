<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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

use CRM_Banking_ExtensionUtil as E;

class CRM_Banking_Helpers_Logger {

  /** singleton */
  private static $_singleton = NULL;

  /** currenlty active log level */
  protected $log_level = 'off';

  /** currenlty active log level */
  protected $log_file_handle = NULL;

  /** timers can be used to time stuff */
  protected $timers = array();

  /**
   * get Logger object
   */
  public static function getLogger() {
    if (self::$_singleton === NULL) {
      self::$_singleton = new CRM_Banking_Helpers_Logger();
    }
    return self::$_singleton;
  }



  /**
   * Constructor
   */
  protected function __construct() {
    // read log level
    $all_levels = self::getLoglevels();
    $this->log_level = CRM_Core_BAO_Setting::getItem('CiviBanking', 'banking_log_level');
    if (!isset($all_levels[$this->log_level])) {
      // invalid log level -> fall back to 'off'
      $this->log_level = 'off';
    }

    // init timers
    $this->timers = array();

    // create log file
    $log_file = CRM_Core_BAO_Setting::getItem('CiviBanking', 'banking_log_file');
    if (!empty($log_file)) {
      // log to file:
      if (substr($log_file, 0, 1) != '/') {
        // not an absolute path, prepend log folder
        $config = CRM_Core_Config::singleton();
        $log_file = "{$config->configAndLogDir}{$log_file}";
      }
      $this->log_file_handle = fopen($log_file, 'a');
    }
  }

  /**
   * set a timer for later use with logTime
   */
  public function setTimer($name = 'default') {
    $this->timers[$name] = microtime(TRUE);
  }

  /**
   * set a timer for later use with logTime
   */
  public function logTime($process_name, $name = 'default') {
    $time = (microtime(TRUE) - $this->timers[$name]);
    $time_ms = (int) ($time * 1000.0);
    $this->logDebug("Process '{$process_name}' took {$time_ms}ms.");
  }

  public function logDebug($message, $reference = NULL) {
    return $this->log($message, 'debug', $reference);
  }

  public function logInfo($message, $reference = NULL) {
    return $this->log($message, 'info', $reference);
  }

  public function logWarn($message, $reference = NULL) {
    return $this->log($message, 'warn', $reference);
  }

  public function logError($message, $reference = NULL) {
    return $this->log($message, 'error', $reference);
  }

  /**
   * log message
   *
   * @param $level  one of 'error', 'warn', 'info', 'debug'
   */
  public function log($message, $level = 'info', $reference = NULL) {
    if ($this->log_level == 'off') return;

    switch ($level) {
      case 'off':
        return;

      case 'error':
        if   ($this->log_level == 'error'
           || $this->log_level == 'warn'
           || $this->log_level == 'info'
           || $this->log_level == 'debug') {
          break;
        } else {
          return;
        }

      case 'warn':
        if   ($this->log_level == 'warn'
           || $this->log_level == 'info'
           || $this->log_level == 'debug') {
          break;
        } else {
          return;
        }

      case 'info':
        if   ($this->log_level == 'info'
           || $this->log_level == 'debug') {
          break;
        } else {
          return;
        }

      case 'debug':
        if ($this->log_level == 'debug') {
          break;
        } else {
          return;
        }

      default:
        $this->logError("Unknown log level '{$level}' given. Ignored.", $reference);
        return;
    }

    // now log it
    if ($this->log_file_handle) {
      fwrite($this->log_file_handle, date('Y-m-d H:i:s') . ' ' . $message . "\n");
    } else {
      error_log("org.project60.banking: " . $message);
    }
  }

  /**
   * get a list of all log levels
   */
  public static function getLoglevels() {
    return array(
      'off'   => E::ts('No Logging'),
      'debug' => E::ts('Debug'),
      'info'  => E::ts('Info'),
      'warn'  => E::ts('Warnings'),
      'error' => E::ts('Errors'),
    );
  }

}
