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

class CRM_Banking_PluginImpl_Importer_Fixed extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */
  public function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->defaults)) {
      $config->defaults = [];
    }
    if (!isset($config->generic_rules)) {
      $config->generic_rules = [];
    }
  }

  /**
   * will be used to avoid multiple account lookups
   */
  protected array $account_cache = [];

  /**
   * This will be used to suppress duplicates within the same statement
   *  when automatically generating references
   */
  protected $bank_reference_cache = [];

  /**
   * file handle to the file to be imported (opened read-only)
   */
  protected $file_handle = NULL;


  /**
   * current statment data
   */
  protected $data = NULL;


  /**
   * current transaction data
   */
  protected $tx_data = NULL;

  /**
   * current RAW transaction data
   */
  protected $tx_raw_lines = NULL;

  /**
   * line number currently processed
   */
  protected $line_nr = 0;

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  public static function displayName() {
    return 'Fixed Width TXT Importer';
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

  /**
   * Test if the given file can be imported
   *
   * @var
   * @return TODO: data format?
   */
  public function probe_file($file_path, $params) {
    // TODO: use sentinel if exists
    return TRUE;
  }

  /**
   * Imports the given TXT file
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  public function import_file($file_path, $params) {
  // phpcs:disable
    // Init
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));

    $this->file_handle = fopen($file_path, 'r');
    // TODO: error handling

    // all good -> start creating stament
    $this->line_nr = 0;
    $this->data = [];
    foreach ($config->defaults as $key => $value) {
      $this->data[$key] = $value;
    }

    $batch = $this->openTransactionBatch();
    $line = NULL;

    while (($line = fgets($this->file_handle)) !== FALSE) {
      $this->line_nr += 1;

      // check encoding if necessary
      if (isset($config->encoding)) {
        if (in_array($config->encoding, mb_list_encodings())) {
          $line = mb_convert_encoding($line, mb_internal_encoding(), $config->encoding);
        }
        elseif (extension_loaded('iconv')) {
          $line = iconv($config->encoding, mb_internal_encoding(), $line);
        }
        else {
          trigger_error("Unknown encoding {$config->encoding}, try enabling the iconv PHP extension", E_USER_ERROR);
        }
      }

      $this->apply_rules('generic_rules', $line, $params);

      // add line to current tx (if not already there)
      if ($this->tx_raw_lines && end($this->tx_raw_lines) != $line) {
        $this->tx_raw_lines[] = $line;
      }
    }
    fclose($this->file_handle);

    // finish statement object
    if ($this->getCurrentTransactionBatch()->tx_count) {
      $this->getCurrentTransactionBatch()->reference = 'TXT-File {md5}';

      $this->closeTransactionBatch(TRUE);
    }
    else {
      $this->closeTransactionBatch(FALSE);
    }
    $this->reportDone();
  }

  /**
   * Processes and imports one individual payment node
   */
  protected function apply_rules($rules_name, $line, &$params) {
    $config = $this->_plugin_config;

    if (empty($config->$rules_name) || !is_array($config->$rules_name)) {
      // TODO: error handling
      return;
    }

    foreach ($config->$rules_name as $rule) {
      $this->apply_rule($rule, $line, $params);
    }
  }

  /**
   * executes ONE import rule
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  protected function apply_rule($rule, &$line, &$params) {
  // phpcs:enable
    switch ($rule->type) {
      case 'extract':
        if (strpos($rule->position, '-') !== FALSE) {
          list($pos_from, $pos_to) = explode('-', $rule->position);
          $length = $pos_to - $pos_from + 1;
        }
        elseif (strpos($rule->position, '+') !== FALSE) {
          list($pos_from, $length) = explode('+', $rule->position);
        }
        // else: TODO: error handling

        $value = mb_substr($line, $pos_from - 1, $length);
        $this->storeValue($rule->to, $value);
        break;

      case 'apply_rules':
        if (empty($rule->regex) || preg_match($rule->regex, $line)) {
          $this->apply_rules($rule->rules, $line, $params);
        }
        break;

      case 'replace':
        $value = $this->getValue($rule->from);
        $new_value = preg_replace($rule->search, $rule->replace, $value);
        $this->storeValue($rule->to, $new_value);
        break;

      case 'trim':
        $value = trim($this->getValue($rule->from));
        $this->storeValue($rule->to, $value);
        break;

      case 'copy':
        /**
         *
         * copy :
         * Copies a value from one field to another
         *
         * Usage example:
         *
         * "type": "copy"
         * "from": "tx.field1",
         * "to": "tx.field2"
         */
        $value = $this->getValue($rule->from);
        $this->storeValue($rule->to, $value);
        break;

      case 'clear_prepending':
        /**
         * clear_prepending :
         * Easier way to clears zeros(0) from the start of a string
         *
         * Usage example:
         *
         * "type": "clear_prepending",
         * "from": "tx.number",
         * "to": "tx.cleared_number"
         *
         */
        $value = ltrim($this->getValue($rule->from), '0');
        $this->storeValue($rule->to, $value);
        break;

      case 'line_nr':
        $this->storeValue($rule->to, $this->line_nr);
        break;

      case 'date':
        $value = $this->getValue($rule->from);
        $datetime = DateTime::createFromFormat($rule->format, $value);
        if ($datetime) {
          if (isset($rule->store_format)) {
            $date_value = $datetime->format($rule->store_format);
          }
          else {
            $date_value = $datetime->format('YmdHis');
          }
          $this->storeValue($rule->to, $date_value);
        }
        // else: TODO: error handling date format wrong
        break;

      case 'append':
        /**
         * APPEND appends the string to a given field. Separator is newline \n
         *
         * Usage example:
         *
         * "type": "append",
         * "from": "tx.comment",
         * "to": "tx.purpose",
         *
         */
        $from = $this->getValue($rule->from);
        $this->storeValue($rule->to, $this->getValue($rule->to) . "\n" . $from);
        break;

      case 'transaction:open':
        $this->closeTransaction($line, $params);
        $this->openTransaction($line);
        break;

      case 'transaction:close':
        $this->closeTransaction($line, $params);
        break;

      default:
        // TODO error handling
        break;
    }
  }

  /**
   * @TODO: document
   */
  protected function getValue($name) {
    if (substr($name, 0, 3) == 'tx.') {
      return $this->tx_data[substr($name, 3)];

    }
    elseif (substr($name, 0, 9) == 'tx_batch.') {
      return $this->data[substr($name, 9)];

    }
    // else: TODO: error handling

    return NULL;
  }

  /**
   * @TODO: document
   */
  protected function storeValue($name, $value) {
    if (substr($name, 0, 3) == 'tx.') {
      $this->tx_data[substr($name, 3)] = $value;
    }
    else {
      // TODO: remove prefix? other prefixes?
      $this->data[$name] = $value;
    }
  }

  /**
   * @TODO: document
   */
  protected function openTransaction(&$line) {
    $this->tx_data = [];
    $this->tx_raw_lines = [$line];

    // copy all tx.* fields from general data
    foreach ($this->data as $key => $value) {
      if (substr($key, 0, 3) == 'tx.') {
        $this->tx_data[substr($key, 3)] = $value;
      }
    }
  }

  /**
   * @TODO: document
   */
  protected function closeTransaction(&$line, &$params) {
    if (empty($this->tx_data)) {
      return;
    }

    $btx = $this->tx_data;
    $btx['data_raw'] = implode('||', $this->tx_raw_lines);

    // TODO: progress
    $progress = 0.0;

    // look up the bank accounts
    $this->lookupBankAccounts($btx);

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

    $duplicate = $this->checkAndStoreBTX($btx, $progress, $params);

    // reset data
    $this->tx_data = NULL;
    $this->tx_raw_lines = NULL;
  }

}
