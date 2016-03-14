<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_Importer_Fixed extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->defaults))      $config->defaults      = array();
    if (!isset($config->generic_rules)) $config->generic_rules = array();
  }

  /**
   * will be used to avoid multiple account lookups
   */
  protected $account_cache = array();

  /**
   * This will be used to suppress duplicates within the same statement
   *  when automatically generating references
   */
  protected $bank_reference_cache = array();

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
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'Fixed Width TXT Importer';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  static function does_import_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_import_stream()
  {
    return false;
  }

  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  function probe_stream( $params )
  {
    return false;
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_stream( $params )
  {
    $this->reportDone(ts("Importing streams not supported by this plugin."));
  }

  /** 
   * Test if the given file can be imported
   * 
   * @var 
   * @return TODO: data format? 
   */
  function probe_file( $file_path, $params )
  {
    // TODO: use sentinel if exists
  }


  /** 
   * Imports the given XML file
   *
   */
  function import_file( $file_path, $params )
  {
    // Init
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));

    $this->file_handle = fopen($file_path, 'r');
    // TODO: error handling

    // all good -> start creating stament 
    $this->data = array();
    $batch = $this->openTransactionBatch();

    while ( ($line == fgets($this->file_handle)) !== false) {
      $this->apply_rules('generic_rules', $line, $params);

    }
    fclose($this->file_handle);

    // finish statement object
    if ($this->getCurrentTransactionBatch()->tx_count) {
      // copy all data entries starting with tx.batch into the batch
      if (!empty($data['tx_batch.reference'])) {
        $this->getCurrentTransactionBatch()->reference = $data['tx_batch.reference'];
      } else {
        $this->getCurrentTransactionBatch()->reference = "XML-File {md5}";
      }

      if (!empty($data['tx_batch.sequence']))
        $this->getCurrentTransactionBatch()->sequence = $data['tx_batch.sequence'];
      if (!empty($data['tx_batch.starting_date']))
        $this->getCurrentTransactionBatch()->starting_date = $data['tx_batch.starting_date'];
      if (!empty($data['tx_batch.ending_date']))
        $this->getCurrentTransactionBatch()->ending_date = $data['tx_batch.ending_date'];

      $this->closeTransactionBatch(TRUE);
    } else {
      $this->closeTransactionBatch(FALSE);
    }
    $this->reportDone();
  }

  /**
   * Processes and imports one individual payment node
   */
  protected function apply_rules($rules_name, $line, &$params) {
    $config = $this->_plugin_config;

    if (empty($config->$rules_name) && is_array($config->$rules_name)) {
      // TODO: error handling
      return;
    }

    foreach ($config->$rules_name as $rule) {
      $this->apply_rule($rule, $params);
    }
  }



  /**
   * executes ONE import rule
   */
  protected function apply_rule($rule, $line, &$params) {

    switch ($rule->type) {
      case 'extract':
        if (strpos($rule->position, '-') !== FALSE) {
          list($pos_from, $pos_to) = split('-', $rule->position);
          $length = $pos_to - $pos_from;
        } elseif (strpos($rule->position, '+') !== FALSE) {
          list($pos_from, $length) = split('+', $rule->position);
        } else {
          // TODO: error handling
        }
        
        $value = substr($line, $pos_from, $length);
        $this->storeValue($value, $rule->to);
        break;

      case 'apply_rules':
        if (preg_match($rule->regex, $line)) {
          $this->apply_rules($rule->rules, $line);
        }
        break;

      case 'transaction:open':
        $this->closeTransaction($params);
        $this->openTransaction();
        break;

      case 'transaction:close':
        $this->closeTransaction($params);
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

    } elseif (substr($name, 0, 9) == 'tx_batch.') { 
      return $this->data[substr($name, 9)];

    } else {
      // TODO: error handling
    }
  }

  /** 
   * @TODO: document
   */
  protected function storeValue($name, $value) {
    if (substr($name, 0, 3) == 'tx.') {
      $this->tx_data[substr($name, 3)] = $value;
    } else {
      // TODO: remove prefix? other prefixes?
      $this->data[$name] -> $value;
    }
  }

  /** 
   * @TODO: document
   */
  protected function openTransaction() {
    $this->tx_data = array();

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
  protected function closeTransaction(&$params) {
    if (empty($this->tx_data)) return;

    // TODO: progress
    $progress = 0.0;

    $duplicate = $this->checkAndStoreBTX($this->tx_data, $progress, $params);
  }

}

