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
class CRM_Banking_PluginImpl_Exporter_CSV extends CRM_Banking_PluginModel_Exporter {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->delimiter)) $config->delimiter = ',';
    if (!isset($config->quotes))    $config->quotes = '"';
    if (!isset($config->header))    $config->header = 1;
    if (!isset($config->columns))   $config->columns = array('txbatch_id', 'tx_id');  // TODO: extend
    if (!isset($config->rules))     $config->rules = array();
    if (!isset($config->filters))   $config->filters = array();
  }

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'CSV Exporter';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  public function does_export_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  public function does_export_stream()
  {
    return false;
  }

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return URL of the resulting file
   */
  public function export_file( $txbatch2ids, $parameters ) {
    $file = $this->_export($txbatch2ids, $parameters);
    return array(
      'path'           => $file,
      'file_name'      => 'export',
      'file_extension' => 'csv',
      'mime_type'      => 'text/csv',
    );
  }

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return bool TRUE if successful
   */
  public function export_stream( $txbatch2ids, $parameters ) {
    $file = $this->_export($txbatch2ids, $parameters);

    // TODO: upload

    return true;
  }


  /**
   * This is the main method where the actual export happens
   * 
   * It will create a temp file, export to that, and then return the local path
   */
  protected function _export($txbatch2ids, $parameters ) {
    $config = $this->_plugin_config;

    // OPEN FILE
    $temp_file = $this->getTempFile();
    $file_sink = fopen($temp_file, 'w');

    // write header, if requested
    if (!empty($config->header)) {
      fputcsv($file_sink, $config->columns, $config->delimiter, $config->quotes);
    }

    foreach ($txbatch2ids as $tx_batch_id => $batch_txns) {
      // todo get information on the batch
      $tx_batch_data = $this->getBatchData($tx_batch_id);

      foreach ($batch_txns as $tx_id) {
        $tx_data = $this->getTxData($tx_id);
        $data_blob = $this->compileDataBlob($tx_batch_data, $tx_data);

        // execute rules
        foreach ($config->rules as $rule) {
          $this->apply_rule($rule, $data_blob);
        }


        // apply filters
        $line_filtered_out = FALSE;
        foreach ($config->filters as $filter) {
          if ($this->runFilter($filter, $data_blob)) {
            $line_filtered_out = TRUE;
            break;
          }
        }
        if ($line_filtered_out) continue;

        // DEBUGGING: error_log(print_r($data_blob,1));

        // write row
        $csv_line = array();
        foreach ($config->columns as $column_name) {
          if (isset($data_blob[$column_name])) {
            $csv_line[] = $data_blob[$column_name];
          } else {
            $csv_line[] = '';
          }
        }
        fputcsv($file_sink, $csv_line, $config->delimiter, $config->quotes);
      }
    }

    // close file
    fclose($file_sink);

    return $temp_file;
  }

  /**
   * execute the given rule on the data_blob
   */
  protected function apply_rule($rule, &$data_blob) {

    if ($rule->type == 'set') {
      // RULE TYPE 'set'
      if (isset($data_blob[$rule->from]) && isset($rule->to)) {
        $data_blob[$rule->to] = $data_blob[$rule->from];
      }

    } elseif ($rule->type == 'amount') {
      // RULE TYPE 'amount'
      if (isset($data_blob[$rule->from])) {
        $amount = $data_blob[$rule->from];
        $currency = $data_blob[$rule->currency];
        $data_blob[$rule->to] = CRM_Utils_Money::format($amount, $currency);
      }

    } elseif ($rule->type == 'lookup') {
      // RULE TYPE 'lookup'
      if (isset($data_blob[$rule->from])) {
        $key_value = $data_blob[$rule->from];
        if (!empty($key_value)) {
          $entity = civicrm_api($rule->entity, 'getsingle', array('version' => 3, $rule->key => $key_value));
          if (!empty($entity['is_error'])) {
            error_log("org.project60.banking.exporter.csv: rule lookup produced error: " . $entity['error_message']);
          } else {
            foreach ($entity as $key => $value) {
              $data_blob[$rule->to . $key] = $value;
            }
          }
        }
      }


    } else {
      error_log("org.project60.banking.exporter.csv: rule type '${$rule->type}' unknown, rule ignored.");
    }
  }
}
