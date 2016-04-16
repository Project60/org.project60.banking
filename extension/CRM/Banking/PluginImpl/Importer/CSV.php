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

// utility function
function _csvimporter_helper_startswith($string, $prefix) {
  return substr($string, 0, strlen($prefix)) === $prefix;
}

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
   */ function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->delimiter))      $config->delimiter = ',';
    if (!isset($config->header))         $config->header = 1;
    if (!isset($config->warnings))       $config->warnings = true;
    if (!isset($config->skip))           $config->skip = 0;
    if (!isset($config->line_filter))    $config->line_filter = NULL;
    if (!isset($config->defaults))       $config->defaults = array();
    if (!isset($config->rules))          $config->rules = array();
    if (!isset($config->drop_columns))   $config->drop_columns = array();
  }

  /**
   * will be used to avoid multiple account lookups
   */
  protected $account_cache = array();

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'CSV Importer';
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
   * Test if the given file can be imported
   * 
   * @var 
   * @return TODO: data format? 
   */
  function probe_file( $file_path, $params )
  {
    $config = $this->_plugin_config;
    if (empty($config->sentinel)) return true; // no sentinel specified... there's nothing we can do.

    // the sentinel is used to verfiy, that the file is of the expected format
    $file = fopen($file_path, 'r');
    $probe_data = fread($file, 1024);
    fclose($file);

    // check encoding if necessary
    if (isset($config->encoding)) {
      $probe_data = mb_convert_encoding($probe_data, mb_internal_encoding(), $config->encoding);
    }

    // end verify this matches the sentinel
    $sentinel = mb_convert_encoding($config->sentinel, mb_internal_encoding());
    return preg_match($sentinel, $probe_data);
  }


  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_file( $file_path, $params )
  {
    // begin
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));
    $file_size = filesize($file_path);
    $file = fopen($file_path, 'r');
    $line_nr = 0;
    $bytes_read = 0;
    $header = array();

    $batch = $this->openTransactionBatch();
    while (($line = fgetcsv($file, 0, $config->delimiter)) !== FALSE) {
      // update stats
      $line_nr += 1;
      foreach ($line as $item) $bytes_read += strlen($item);
      $bytes_read += sizeof($line) * sizeof($config->delimiter);

      // check if we want to skip line (by count)
      if ($line_nr <= $config->skip) continue;

      // check if we want to skip line (by filter)
      if (!empty($config->line_filter)) {
        $full_line = trim(implode(',', $line));
        if (!preg_match($config->line_filter, $full_line)) {
          $config->header += 1;  // bump line numbers if filtered out
          continue;
        }
      }

      // check encoding if necessary
      if (isset($config->encoding)) {
        $decoded_line = array();
        foreach ($line as $item) {
          array_push($decoded_line, mb_convert_encoding($item, mb_internal_encoding(), $config->encoding));
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
        if (sizeof($header)==0) {
          $header = $line;  
        }
      } else {
        // import lime
        $this->import_line($line, $line_nr, ($bytes_read/$file_size), $header, $params);
      }
    }
    fclose($file); 

    //TODO: customize batch params
    
    if ($this->getCurrentTransactionBatch()->tx_count) {
      // we have transactions in the batch -> save
      if ($config->title) {
        // the config defines a title, replace tokens
        $this->getCurrentTransactionBatch()->reference = $config->title;
      } else {
        $this->getCurrentTransactionBatch()->reference = "CSV-File {md5}";
      }

      $this->closeTransactionBatch(TRUE);
    } else {
      $this->closeTransactionBatch(FALSE);
    }
    $this->reportDone();
  }

  protected function import_line($line, $line_nr, $progress, $header, $params) {
    $config = $this->_plugin_config;
    
    // generate entry data
    $raw_data = implode(";", $line);
    $btx = array(
      'version' => 3,
      'currency' => 'EUR',
      'type_id' => 0,                               // TODO: lookup type ?
      'status_id' => 0,                             // TODO: lookup status new
      'data_raw' => $raw_data,
      'sequence' => $line_nr-$config->header,
    );

    // set default values from config:
    foreach ($config->defaults as $key => $value) {
      $btx[$key] = $value;
    }

    // execute rules from config:
    foreach ($config->rules as $rule) {
      try {
        $this->apply_rule($rule, $line, $btx, $header);
      } catch (Exception $e) {
        $this->reportProgress($progress, sprintf(ts("Rule '%s' failed. Exception was %s"), $rule, $e->getMessage()));
      }
    }

    // run filters
    if (isset($config->filter) && is_array($config->filter)) {
      foreach ($config->filter as $filter) {
        if ($filter->type=='string_positive') {
          // only accept string matches
          $value1 = $this->getValue($filter->value1, $btx, $line, $header);
          $value2 = $this->getValue($filter->value2, $btx, $line, $header);
          if ($value1 != $value2) {
            $this->reportProgress($progress, sprintf("Skipped line %d", $line_nr-$config->header));
            return;
          }
        }
      }
    }

    // look up the bank accounts
    foreach ($btx as $key => $value) {
      // check for NBAN_?? or IBAN endings
      if (preg_match('/^_.*NBAN_..$/', $key) || preg_match('/^_.*IBAN$/', $key)) {
        // this is a *BAN entry -> look it up
        if (!isset($this->account_cache[$value])) {
          $result = civicrm_api('BankingAccountReference', 'getsingle', array('version' => 3, 'reference' => $value));
          if (!empty($result['is_error'])) {
            $this->account_cache[$value] = NULL;
          } else {
            $this->account_cache[$value] = $result['ba_id'];
          }
        }

        if ($this->account_cache[$value] != NULL) {
          if (substr($key, 0, 7)=="_party_") {
            $btx['party_ba_id'] = $this->account_cache[$value];  
          } elseif (substr($key, 0, 1)=="_") {
            $btx['ba_id'] = $this->account_cache[$value];  
          }
        }
      }
    }

    // do some post processing
    if (!isset($config->bank_reference)) {
      // set MD5 hash as unique reference
      $btx['bank_reference'] = md5($raw_data);
    } else {
      // otherwise use the template
      $bank_reference = $config->bank_reference;
      $tokens = array(); 
      preg_match('/\{([^\}]+)\}/', $bank_reference, $tokens);
      foreach ($tokens as $key => $token_name) {
        if (!$key) continue;  // match#0 is not relevant
        $token_value = isset($btx[$token_name])?$btx[$token_name]:'';
        $bank_reference = str_replace("{{$token_name}}", $token_value, $bank_reference);
      }
      $btx['bank_reference'] = $bank_reference;
    }    

    // prepare $btx: put all entries, that are not for the basic object, into parsed data
    $btx_parsed_data = array();
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

    $this->reportProgress($progress, sprintf("Imported line %d", $line_nr-$config->header));
  }

  /**
   * Extract the value for the given key from the resources (line, btx).
   */
  protected function getValue($key, $btx, $line=NULL, $header=array()) {
    // get value
    if (_csvimporter_helper_startswith($key, '_constant:')) {
      return substr($key, 10);
    } else if ($line && is_int($key)) {
      return $line[$key];
    } else {
      $index = array_search($key, $header);
      if ($index!==FALSE) {
        if (isset($line[$index])) {
          return $line[$index];  
        } else {
          // this means, that the column does exist in the header, 
          //  but not in this row => bad CSV
          return NULL;
        }
      } elseif (isset($btx[$key])) {
        // this is not in the line, maybe it's already in the btx
        return $btx[$key];
      } else {
        if ($this->_plugin_config->warnings) {
          error_log("org.project60.banking: CSVImporter - Cannot find source '$key' for rule or filter.");
        }
      }
    }
    return '';
  }

  /**
   * executes an import rule
   */
  protected function apply_rule($rule, $line, &$btx, $header) {

    // get value
    $value = $this->getValue($rule->from, $btx, $line, $header);

    // check if-clause
    if (isset($rule->if)) {
      if (_csvimporter_helper_startswith($rule->if, 'equalto:')) {
        $params = explode(":", $rule->if);
        if ($value != $params[1]) return;
      } elseif (_csvimporter_helper_startswith($rule->if, 'matches:')) {
        $params = explode(":", $rule->if);
        if (!preg_match($params[1], $value)) return;
      } else {
        print_r("CONDITION (IF) TYPE NOT YET IMPLEMENTED");
        return;
      }
    }

    // execute the rule
    if (_csvimporter_helper_startswith($rule->type, 'set')) {
      // SET is a simple copy command:
      $btx[$rule->to] = $value;

    } elseif (_csvimporter_helper_startswith($rule->type, 'append')) {
      // APPEND appends the string to a give value
      if (!isset($btx[$rule->to])) $btx[$rule->to] = '';
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user defined a concat string
        $btx[$rule->to] = $btx[$rule->to].$params[1].$value;
      } else {
        // default concat string is " "
        $btx[$rule->to] = $btx[$rule->to]." ".$value;
      }

    } elseif (_csvimporter_helper_startswith($rule->type, 'trim')) {
      // TRIM will strip the string of 
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user provided a the trim parameters
        $btx[$rule->to] = trim($value, $params[1]);
      } else {
        $btx[$rule->to] = trim($value);
      }

    } elseif (_csvimporter_helper_startswith($rule->type, 'replace')) {
      // REPLACE will replace a substring
      $params = explode(":", $rule->type);
      $btx[$rule->to] = str_replace($params[1], $params[2], $value);

    } elseif (_csvimporter_helper_startswith($rule->type, 'format')) {
      // will use the sprintf format
      $params = explode(":", $rule->type);
      $btx[$rule->to] = sprintf($params[1], $value);

    } elseif (_csvimporter_helper_startswith($rule->type, 'constant')) {
      // will just set a constant string
      $btx[$rule->to] = $rule->from;

    } elseif (_csvimporter_helper_startswith($rule->type, 'strtotime')) {
      // STRTOTIME is a date parser
      $params = explode(":", $rule->type, 2);
      if (isset($params[1])) {
        // the user provided a date format
        $datetime = DateTime::createFromFormat($params[1], $value);
        if ($datetime) {
          $btx[$rule->to] = $datetime->format('YmdHis');  
        }
      } else {
        $btx[$rule->to] = date('YmdHis', strtotime($value));
      }

    } elseif (_csvimporter_helper_startswith($rule->type, 'amount')) {
      // AMOUNT will take care of currency issues, like "," instead of "."
      $btx[$rule->to] = str_replace(",", ".", $value);

    } elseif (_csvimporter_helper_startswith($rule->type, 'regex:')) {
      // REGEX will extract certain values from the line
      $pattern = substr($rule->type, 6);
      $matches = array();
      if (preg_match($pattern, $value, $matches)) {
        // we found it!
        $btx[$rule->to] = $matches[1];
      } else {
        // check, if we should warn: (not set = 'warn' for backward compatibility)
        if (!isset($rule->warn) || $rule->warn) {
          $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE, 
            sprintf(ts("Pattern '%s' was not found in entry '%s'."), $pattern, $value));          
        }
      }

    } else {
      print_r("RULE TYPE NOT YET IMPLEMENTED");
    }    
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
}

