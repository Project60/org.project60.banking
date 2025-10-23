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

use CRM_Banking_ExtensionUtil as E;

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_Importer_CSV extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  public function __construct($config_name) {
  // phpcs:enable
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->delimiter)) {
      $config->delimiter = ',';
    }
    if (!isset($config->enclosure)) {
      $config->enclosure = '"';
    }
    if (!isset($config->escape)) {
      $config->escape = '\\';
    }
    if (!isset($config->header)) {
      $config->header = 1;
    }
    if (!isset($config->warnings)) {
      $config->warnings = TRUE;
    }
    if (!isset($config->skip)) {
      $config->skip = 0;
    }
    if (!isset($config->line_filter)) {
      $config->line_filter = NULL;
    }
    if (!isset($config->defaults)) {
      $config->defaults = [];
    }
    if (!isset($config->rules)) {
      $config->rules = [];
    }
    if (!isset($config->drop_columns)) {
      $config->drop_columns = [];
    }
    if (!isset($config->line_filter_use_delimiter)) {
      $config->line_filter_use_delimiter = FALSE;
    }
  }

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  public static function displayName() {
    return 'CSV Importer';
  }

  /**
   * Report if the plugin is capable of importing files
   *
   * @return bool
   */
  public static function does_import_files() {
    return TRUE;
  }

  /**
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   *
   * @return bool
   */
  public static function does_import_stream() {
    return FALSE;
  }

  /**
   * Test if the given file can be imported
   *
   * @param string $file_path
   *   local file path
   *
   * @param array $params
   *   upload parameters, e.g. 'source' would have the file name
   *
   * @return boolean true if file is accepted
   */
  public function probe_file($file_path, $params) {
    $config = $this->_plugin_config;

    if (!empty($config->sentinel)) {
      // the sentinel is used to verify, that the file is of the expected format
      $file = fopen($file_path, 'r');

      // skip BOM if present
      $possible_bom_hex = bin2hex(fgets($file, 4));
      // Skip BOM if present
      if ($possible_bom_hex !== 'efbbbf') {
        // Or rewind pointer to start of file
        rewind($file);
      }

      $probe_data = fgets($file, 1024);
      fclose($file);

      // check encoding if necessary...
      if (isset($config->encoding)) {
        $probe_data = mb_convert_encoding($probe_data, mb_internal_encoding(), $config->encoding);
      }

      // and verify this matches the sentinel:
      $sentinel = mb_convert_encoding($config->sentinel, mb_internal_encoding());
      if (!preg_match($sentinel, $probe_data)) {
        $this->logMessage("Uploaded file's content doesn't match the sentinel.", 'warning');
        return FALSE;
      }
    }

    // check if the file name is accepted
    if (!empty($config->filename_sentinel)) {
      if (empty($params['source'])) {
        $this->logMessage('filename_sentinel provided, but no file name present!', 'warning');
      }
      else {
        if (!preg_match($config->filename_sentinel, $params['source'])) {
          $this->logMessage("Uploaded file's name doesn't match the filename sentinel.", 'warning');
          return FALSE;
        }
      }
    }

    // all good
    return TRUE;
  }

  /**
   * Import the given file
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.TooHigh
   */
  public function import_file($file_path, $params) {
    // begin
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));
    $file_size = filesize($file_path);
    $file = fopen($file_path, 'r');
    $line_nr = 0;
    $bytes_read = 0;
    $header = [];

    // skip BOM if present
    $possible_bom_hex = bin2hex(fgets($file, 4));
    // Skip BOM if present
    if ($possible_bom_hex !== 'efbbbf') {
      // Or rewind pointer to start of file
      rewind($file);
    }

    if (!empty($config->line_filter_use_delimiter)) {
      $separator = $config->delimiter;
    }
    else {
      // Legacy: use comma to rejoin row.
      // @see https://github.com/Project60/org.project60.banking/issues/443
      $separator = ',';
    }

    // Count filtered out lines.
    $filtered_out = 0;

    $batch = $this->openTransactionBatch();
    while (($line = fgetcsv($file, 0, $config->delimiter, $config->enclosure, $config->escape)) !== FALSE) {
      // update stats
      $line_nr += 1;
      foreach ($line as $item) {
        // If the line read from the file was empty, $line contains [NULL].
        // @todo Is this the correct way to handle it?
        $bytes_read += strlen($item ?? '');
      }
      $bytes_read += count($line) * strlen($config->delimiter);

      // check if we want to skip line (by count)
      if ($line_nr <= $config->skip) {
        continue;
      }

      // check if we want to skip line (by filter)
      if (!empty($config->line_filter)) {
        $full_line = trim(implode($separator, $line));
        if (!preg_match($config->line_filter, $full_line)) {
          // bump line numbers if filtered out
          $config->header += 1;
          $filtered_out++;
          continue;
        }
      }

      // check encoding if necessary
      if (isset($config->encoding)) {
        $decoded_line = [];
        foreach ($line as $item) {
          if (in_array($config->encoding, mb_list_encodings())) {
            array_push($decoded_line, mb_convert_encoding($item, mb_internal_encoding(), $config->encoding));
          }
          elseif (extension_loaded('iconv')) {
            array_push($decoded_line, iconv($config->encoding, mb_internal_encoding(), $item));
          }
          else {
            trigger_error("Unknown encoding {$config->encoding}, try enabling the iconv PHP extension", E_USER_ERROR);
          }
        }
        $line = $decoded_line;
      }

      // exclude ignored columns from further processing
      if (!empty($config->drop_columns)) {
        foreach ($config->drop_columns as $column) {
          $index = array_search($column, $header);
          if ($index !== FALSE) {
            unset($line[$index]);
          }
        }
      }

      if ($line_nr == $config->header) {
        // parse header
        if (count($header) == 0) {
          $header = $line;
        }
      }
      else {
        // import line
        $last_btx = $this->import_line($line, $line_nr, ($bytes_read / $file_size), $header, $params);
      }
    }
    fclose($file);

    //TODO: customize batch params

    if ($this->getCurrentTransactionBatch()->tx_count) {
      // we have transactions in the batch -> save
      if ($config->title) {
        // the config defines a title, use that
        $reference = $config->title;
        if (isset($last_btx)) {
          // replace tokens from last BTX
          $last_btx_data = json_decode($last_btx['data_parsed'], TRUE);
          preg_match_all('/\{([^\}]+)\}/', $config->title, $matches);
          if (isset($matches[1])) {
            // exclude the default tokens
            foreach ($matches[1] as $token_name) {
              if (!in_array($token_name, ['md5', 'starting_date', 'ending_date'])) {
                $token_value = '';
                if (isset($last_btx[$token_name])) {
                  $token_value = $last_btx[$token_name];
                }
                elseif (isset($last_btx_data[$token_name])) {
                  $token_value = $last_btx_data[$token_name];
                }
                $reference = str_replace("{{$token_name}}", $token_value, $reference);
              }
            }
          }

        }
        $this->getCurrentTransactionBatch()->reference = $reference;

      }
      else {
        $this->getCurrentTransactionBatch()->reference = 'CSV-File {md5}';
      }

      $this->closeTransactionBatch(TRUE);
    }
    else {
      $this->closeTransactionBatch(FALSE);
    }

    if ($filtered_out > 0) {
      $this->reportProgress(1.0, E::ts('Filtered out %1 lines', [1 => $filtered_out]));
    }
    $this->reportDone();
  }

  /**
   * Will import the given CSV data line
   *
   * @param array $line
   * @param integer $line_nr
   * @param float $progress
   * @param array $header
   * @param array $params
   * @return array recent btx data structure
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  protected function import_line($line, $line_nr, $progress, $header, $params) {
  // phpcs:enable
    $config = $this->_plugin_config;

    // generate entry data
    $raw_data = implode(';', $line);
    $btx = [
      'version' => 3,
      'currency' => 'EUR',
    // TODO: lookup type ?
      'type_id' => 0,
    // TODO: lookup status new
      'status_id' => 0,
      'data_raw' => $raw_data,
      'sequence' => $line_nr - $config->header,
    ];

    // set default values from config:
    foreach ($config->defaults as $key => $value) {
      $btx[$key] = $value;
    }

    // execute rules from config:
    foreach ($config->rules as $rule) {
      try {
        $this->apply_rule($rule, $line, $btx, $header, $params);
      }
      catch (Exception $e) {
        $this->reportProgress($progress, sprintf(E::ts("Rule '%s' failed. Exception was %s"), $rule, $e->getMessage()));
      }
    }

    // run filters
    if (isset($config->filter) && is_array($config->filter)) {
      foreach ($config->filter as $filter) {
        if ($filter->type == 'string_positive') {
          // only accept string matches
          $value1 = $this->getValue($filter->value1, $btx, $line, $header, $params);
          $value2 = $this->getValue($filter->value2, $btx, $line, $header, $params);
          if ($value1 != $value2) {
            $this->reportProgress($progress, sprintf('Skipped line %d', $line_nr - $config->header));
            return $btx;
          }
        }
      }
    }

    // look up the bank accounts
    $this->lookupBankAccounts($btx);

    // do some post processing
    if (!isset($config->bank_reference)) {
      // set MD5 hash as unique reference
      $btx['bank_reference'] = md5($raw_data);
    }
    else {
      // otherwise use the template
      $bank_reference = $config->bank_reference;
      preg_match_all('/\{([^\}]+)\}/', $bank_reference, $matches);
      if (isset($matches[1])) {
        foreach ($matches[1] as $token_name) {
          $token_value = isset($btx[$token_name]) ? $btx[$token_name] : '';
          $bank_reference = str_replace("{{$token_name}}", $token_value, $bank_reference);
        }
      }
      $btx['bank_reference'] = $bank_reference;
    }

    // prepare $btx: put all entries, that are not for the basic object, into parsed data
    $btx_parsed_data = [];
    foreach ($btx as $key => $value) {
      if (!in_array($key, $this->_primary_btx_fields)) {
        // this entry has to be moved to the $btx_parsed_data records
        $btx_parsed_data[$key] = $value;
        unset($btx[$key]);
      }
    }
    $btx['data_parsed'] = json_encode($btx_parsed_data);

    // and finally write it into the DB
    $duplicate = $this->checkAndStoreBTX($btx, $progress, $params);
    // TODO: process duplicates or failures?

    $this->reportProgress($progress, sprintf('Imported line %d', $line_nr - $config->header));
    return $btx;
  }

  /**
   * Extract the value for the given key from the resources (line, btx).
   */
  protected function getValue($key, $btx, $line = NULL, $header = [], $params = []) {
    // get value
    if ($this->startsWith($key, '_constant:')) {
      return substr($key, 10);
    }
    elseif ($this->startsWith($key, '_params:')) {
      $param_name = substr($key, 8);
      return $params[$param_name] ?? '';
    }
    elseif ($line && is_int($key)) {
      return $line[$key];
    }
    else {
      $index = array_search($key, $header);
      if ($index !== FALSE) {
        if (isset($line[$index])) {
          return $line[$index];
        }
        else {
          // this means, that the column does exist in the header,
          //  but not in this row => bad CSV
          return NULL;
        }
      }
      elseif (isset($btx[$key])) {
        // this is not in the line, maybe it's already in the btx
        return $btx[$key];
      }
      else {
        if ($this->_plugin_config->warnings) {
          error_log("org.project60.banking: CSVImporter - Cannot find source '$key' for rule or filter.");
        }
      }
    }
    return '';
  }

  /**
   * executes an import rule
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
   */
  protected function apply_rule($rule, $line, &$btx, $header, $params) {
  // phpcs:enable
    // get value
    $value = $this->getValue($rule->from, $btx, $line, $header, $params);

    // check if-clause
    if (isset($rule->if)) {
      if ($this->startsWith($rule->if, 'equalto:')) {
        $params = explode(':', $rule->if);
        if ($value != $params[1]) {
          return;
        }
      }
      elseif ($this->startsWith($rule->if, 'matches:')) {
        $params = explode(':', $rule->if, 2);
        if (!preg_match($params[1], $value)) {
          return;
        }
      }
      else {
        print_r('CONDITION (IF) TYPE NOT YET IMPLEMENTED');
        return;
      }
    }

    // execute the rule
    if ($this->startsWith($rule->type, 'set')) {
      // SET is a simple copy command:
      $btx[$rule->to] = $value;

    }
    elseif ($this->startsWith($rule->type, 'append')) {
      // APPEND appends the string to a give value
      if (!isset($btx[$rule->to])) {
        $btx[$rule->to] = '';
      }
      $params = explode(':', $rule->type);
      if (isset($params[1])) {
        // the user defined a concat string
        $btx[$rule->to] = $btx[$rule->to] . $params[1] . $value;
      }
      else {
        // default concat string is " "
        $btx[$rule->to] = $btx[$rule->to] . ' ' . $value;
      }

    }
    elseif ($this->startsWith($rule->type, 'trim')) {
      // TRIM will strip the string of
      $params = explode(':', $rule->type);
      if (isset($params[1])) {
        // the user provided a the trim parameters
        $btx[$rule->to] = trim($value, $params[1]);
      }
      else {
        $btx[$rule->to] = trim($value);
      }
    }
    elseif ($this->startsWith($rule->type, 'copy')) {
      // COPY a value to a new btx field
      $btx[$rule->to] = $value;

    }
    elseif ($this->startsWith($rule->type, 'replace')) {
      // REPLACE will replace a substring
      $params = explode(':', $rule->type);
      $btx[$rule->to] = str_replace($params[1], $params[2], $value);

    }
    elseif ($this->startsWith($rule->type, 'format')) {
      // will use the sprintf format
      $params = explode(':', $rule->type);
      $btx[$rule->to] = sprintf($params[1], $value);

    }
    elseif ($this->startsWith($rule->type, 'constant')) {
      // will just set a constant string
      $btx[$rule->to] = $rule->from;

    }
    elseif ($this->startsWith($rule->type, 'strtotime')) {
      // STRTOTIME is a date parser
      $params = explode(':', $rule->type, 2);
      if (isset($params[1])) {
        // the user provided a date format
        $datetime = DateTime::createFromFormat($params[1], $value);
        if ($datetime) {
          $btx[$rule->to] = $datetime->format('YmdHis');
        }
        else {
          if (!empty($value)) {
            $this->logMessage("Couldn't parse date '{$value}'.", 'error');
          }
        }
      }
      else {
        $btx[$rule->to] = date('YmdHis', strtotime($value));
      }

    }
    elseif ($this->startsWith($rule->type, 'align_date')) {
      // ALIGN a date forwards or backwards
      $params = explode(':', $rule->type, 2);
      $offset = ($params[1] == 'backward') ? '-1 day' : '+1 day';
      $btx[$rule->to] = CRM_Utils_BankingToolbox::alignDateTime($value, $offset, explode(',', $rule->skip));

    }
    elseif ($this->startsWith($rule->type, 'amount')) {
      // AMOUNT will take care of currency issues, like "," instead of "."
      $btx[$rule->to] = str_replace(',', '.', $value);

    }
    elseif ($this->startsWith($rule->type, 'largeamount')) {
      // AMOUNT will take care of currency issues, like "," instead of "."
      //remove thousand separator dots (e.g. in "10.000,00")
      $value = preg_replace('/\.(?=[\d\.]*,\d{2}\b)/', '', $value);
      //remove thousand separator commas (e.g. in "10,000.00")
      $value = preg_replace('/,(?=[\d,]*\.\d{2}\b)/', '', $value);
      $btx[$rule->to] = str_replace(',', '.', $value);

    }
    elseif ($this->startsWith($rule->type, 'regex:')) {
      // REGEX will extract certain values from the line
      $pattern = substr($rule->type, 6);
      $matches = [];
      if (preg_match($pattern, $value, $matches)) {
        // we found it!
        $btx[$rule->to] = $matches[1];
      }
      else {
        // check, if we should warn: (not set = 'warn' for backward compatibility)
        if (!isset($rule->warn) || $rule->warn) {
          $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE,
            sprintf(E::ts("Pattern '%s' was not found in entry '%s'."), $pattern, $value));
        }
      }

    }
    else {
      print_r('RULE TYPE NOT YET IMPLEMENTED');
    }
  }

  /**
   * Test if the configured source is available and ready
   *
   * @var
   * @return TODO: data format?
   */
  public function probe_stream($params) {
    return FALSE;
  }

  /**
   * Import the given file
   *
   * @return TODO: data format?
   */
  public function import_stream($params) {
    $this->reportDone(E::ts('Importing streams not supported by this plugin.'));
  }

}
