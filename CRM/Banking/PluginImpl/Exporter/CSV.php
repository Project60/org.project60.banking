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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_Exporter_CSV extends CRM_Banking_PluginModel_Exporter {

  private static $_lookup_cache = array();

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
    if (!isset($config->name))      $config->name = "CiviBanking Transactions";
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
   * @return array of the resulting file
   */
  public function export_file( $txbatch2ids, $parameters ) {
    $file = $this->_export($txbatch2ids, $parameters);
    return array(
      'path'           => $file,
      'file_name'      => $this->_plugin_config->name,
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
        $main_data_blob = $this->compileDataBlob($tx_batch_data, $tx_data);

        // if explode_split is set, repeat the line for each linked contribution
        if (empty($config->explode_split)) {
          // not set? then it's just one line with the contributions
          $line_count = 1;
        } else {
          // set? then it's one line per contribution ID
          $line_count = max(1, (int) CRM_Utils_Array::value('exec_contribution_count', $main_data_blob));
        }

        for ($index = 1; $index <= $line_count; $index++) {
          $data_blob = $main_data_blob; // copy blob to prevent data from one line to the next
          $prefix = 'exec_contribution' . (($index>1)?"_{$index}_":'_');

          // copy the indexed contribution data into the main contribution
          foreach ($main_data_blob as $key => $value) {
            if (substr($key, 0, strlen($prefix)) == $prefix) {
              $data_blob['exec_contribution_' . substr($key, strlen($prefix))] = $value;
            }
          }

          // execute rules
          foreach ($config->rules as $rule) {
            $this->apply_rule($rule, $data_blob);
          }

          // apply filters: [a and b...] or [c and d] or ...  TRUE accepts/passes line
          $filter_pass = empty($config->filters);  // automatically pass for empty filters
          foreach ($config->filters as $AND_clause) {
            $AND_clause_result = TRUE;  // empty set passes
            foreach ($AND_clause as $filter) {
              $AND_clause_result &= $this->runFilter($filter, $data_blob);
            }
            $filter_pass |= $AND_clause_result;
            if ($filter_pass) break;  // one of the OR clauses is true
          }
          if (!$filter_pass) continue;

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
    }

    // close file
    fclose($file_sink);

    return $temp_file;
  }

  /**
   * execute the given rule on the data_blob
   */
  protected function apply_rule($rule, &$data_blob) {

    // read from value
    $from_value = '';
    if (!in_array($rule->type, array('setconstant'))) {
      // this rule requires a from_value
      if (!isset($rule->from)) {
        $this->logMessage("rule's 'from' field not set.", 'warning');
      } else {
        if (!isset($data_blob[$rule->from])) {
          $this->logMessage("'from' field '{$rule->from}' doesn't exist.", 'debug');
        } else {
          $from_value = $data_blob[$rule->from];
        }
      }
    }

    // APPLY RULE
    if ($rule->type == 'set') {
      // RULE TYPE 'set'
      if (isset($from_value) && isset($rule->to)) {
        $data_blob[$rule->to] = $from_value;
      }

    } elseif ($rule->type == 'setconstant') {
      if (isset($rule->value) && isset($rule->to)) {
        $data_blob[$rule->to] = $rule->value;
      }

    } elseif ($rule->type == 'amount') {
      // RULE TYPE 'amount'
      if (isset($from_value)) {
        $amount = $from_value;
        $value_format = isset($rule->format) ? $rule->format : NULL;
        if (isset($rule->currency) && isset($data_blob[$rule->currency])) {
          // render with currency
          $currency = $data_blob[$rule->currency];
          $data_blob[$rule->to] = CRM_Utils_Money::format($amount, $currency, NULL, FALSE, $value_format);
        } else {
          // render without currency (caution: onlyNumber TRUE doesn't work)
          $full_value = CRM_Utils_Money::format($amount, NULL, NULL, FALSE, $value_format);
          // extract bare value
          if (preg_match("/[-0-9.,']+/", $full_value, $match)) {
            $data_blob[$rule->to] = $match[0];
          }
        }
      }

    } elseif ($rule->type =='sprintf') {
      // RULE TYPE 'sprintf'
      if (empty($rule->format)) {
        $this->logMessage("No format set for 'sprintf' rule!", 'error');
      } else {
        // apply sprintf
        $data_blob[$rule->to] = sprintf($rule->format, $from_value);
      }

    } elseif ($rule->type =='date') {
      // RULE TYPE 'date'
      if (empty($rule->format)) {
        // default format
        $data_blob[$rule->to] = date('Y-m-d H:i:s', strtotime($from_value));
      } else {
        $data_blob[$rule->to] = date($rule->format, strtotime($from_value));
      }

    } elseif ($rule->type == 'lookup') {
      // RULE TYPE 'lookup'
      if (!empty($from_value)) {
        // compile params
        if (isset($rule->params)) {
          $params = (array) $rule->params;
        } else {
          $params = array();
        }
        $params[$rule->key] = $data_blob[$rule->from];

        // get data and apply
        $entity = $this->cachedAPILookup($rule->entity, $params);
        foreach ($entity as $key => $value) {
          $data_blob[$rule->to . $key] = $value;
        }
      }

    } else {
      $this->logMessage("rule type '{$rule->type}' unknown, rule ignored.", 'warning');
    }
  }

  /**
   * Provides a cached API lookup
   *
   * @param $entity string entity
   * @param $params array parameters
   * @return mixed
   */
  protected function cachedAPILookup($entity, $params) {
    $key = "{$entity}:" . json_encode($params);
    if (!isset(self::$_lookup_cache[$key])) {
      $this->logMessage("APILookup cache MISS: {$key}", 'debug');
      try {
        self::$_lookup_cache[$key] = civicrm_api3($entity, 'getsingle', $params);
      } catch (Exception $ex) {
        $this->logMessage("Error while looking up {$key} - " . $ex->getMessage(), 'warning');
        self::$_lookup_cache[$key] = array();
      }
    } else {
      $this->logMessage("APILookup cache HIT: {$key}", 'debug');
    }
    return self::$_lookup_cache[$key];
  }

  /**
   * run the filter on the given row
   *
   * @return TRUE if line should be kept
   */
  protected function runFilter($filter, $data_blob) {
    if (empty($filter->type)) {
      $this->logMessage("Incomplete filter with no 'type' detected. Ignored", 'error');
      return TRUE;
    }

    if ($filter->type == 'compare') {
      // FILTER TYPE 'lookup'
      $value1 = (isset($data_blob[$filter->value_1]))?$data_blob[$filter->value_1]:'';
      $value2 = (isset($data_blob[$filter->value_2]))?$data_blob[$filter->value_2]:'';
      if ($filter->comparator == '==') {
        return $value1 == $value2;
      } elseif ($filter->comparator == '!=') {
        return $value1 != $value2;
      } elseif ($filter->comparator == '>') {
        return $value1 > $value2;
      } elseif ($filter->comparator == '>=') {
        return $value1 >= $value2;
      } elseif ($filter->comparator == '<') {
        return $value1 < $value2;
      } elseif ($filter->comparator == '<=') {
        return $value1 <= $value2;
      } else {
        $this->logMessage("filter type '{$filter->type}' has unknown comparator '{$filter->comparator}'. Ignored", 'error');
        return TRUE;
      }

    } else {
      $this->logMessage("filter type '{$filter->type}' unknown, filter ignored.", 'error');
      return TRUE;
    }
  }
}
