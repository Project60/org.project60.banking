<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*  Example config file:
{
  "amounts":  [ "35.00", "(rand(0,20000)-10000)/100" ],
  "purposes": [ "membership", "donation", "buy yourself something nice" ]
}
*/

// utility function
function _startswith($string, $prefix) {
  return substr($string, 0, strlen($prefix)) === $prefix;
}

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_CSVImporter extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->delimiter)) $config->delimiter = ',';
    if (!isset($config->header)) $config->header = 1;
    if (!isset($config->defaults)) $config->defaults = array();
    if (!isset($config->rules)) $config->rules = array();
    if (!isset($config->BIC)) $config->BIC = rand(1000,10000);
  }

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
    // TODO: implement
    return true;
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

      // check encoding if necessary
      if (isset($config->encoding)) {
        $decoded_line = array();
        foreach ($line as $item) {
          array_push($decoded_line, mb_convert_encoding($item, mb_internal_encoding(), $config->encoding));
        }
        $line = $decoded_line;
      }

      if ($line_nr <= $config->header) {
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
      //$this->getCurrentTransactionBatch()->starting_date = 0;
      //$this->getCurrentTransactionBatch()->ending_date = 0;
      $this->closeTransactionBatch(TRUE);
    } else {
      $this->closeTransactionBatch(FALSE);
    }
    $this->reportDone();
  }

  protected function import_line($line, $line_nr, $progress, $header, $params) {
    $config = $this->_plugin_config;
    $this->reportProgress($progress, sprintf("Imported line %d", $line_nr-$config->header));
    
    // generate entry data
    $raw_data = implode(";", $line);
    $btx = array(
      'version' => 3,
      'currency' => 'EUR',
      'type_id' => 0,                               // TODO: lookup type ?
      'status_id' => 0,                             // TODO: lookup status new
      'data_raw' => $raw_data,
      'sequence' => $line_nr-$config->header,
      'bank_reference' => md5($raw_data),           // Paul: if no other reference available, use MD5 of raw line to find duplicates
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
  }

  /**
   * executes an import rule
   */
  protected function apply_rule($rule, $line, &$btx, $header) {

    // get value
    if (is_int($rule->from)) {
      $value = $line[$rule->from];
    } else {
      $index = array_search($rule->from, $header);
      $value = $line[$index];
    }

    // execute the rule
    if (_startswith($rule->type, 'set')) {
      // SET is a simple copy command:
      $btx[$rule->to] = $value;

    } elseif (_startswith($rule->type, 'append')) {
      // APPEND appends the string to a give value
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user defined a concat string
        if (!isset($btx[$rule->to])) $btx[$rule->to] = '';
        $btx[$rule->to] = $btx[$rule->to].$params[1].$value;
      } else {
        // default concat string is " "
        $btx[$rule->to] = $btx[$rule->to]." ".$value;
      }

    } elseif (_startswith($rule->type, 'strtotime')) {
      // STRTOTIME is a date parser
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user provided a date format
        $datetime = DateTime::createFromFormat($params[1], $value);
        $btx[$rule->to] = $datetime->format('Ymd120000');
      } else {
        $datetime = strtotime($value);
        date('Ymd120000', $datetime);
      }

    } elseif (_startswith($rule->type, 'amount')) {
      // AMOUNT will take care of currency issues, like "," instead of "."
      $btx[$rule->to] = str_replace(",", ".", $value);

    } elseif (_startswith($rule->type, 'regex:')) {
      // REGEX will extract certain values from the line
      $pattern = substr($rule->type, 6);
      $matches = array();
      if (preg_match($pattern, $value, $matches)) {
        // we found it!
        $btx[$rule->to] = $matches[1];
      } else {
        $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE, 
          sprintf(ts("Pattern '%s' was not found in entry '%s'."), $pattern, $value));
      }

    } else {
      print_r("RULE TYPE NOT YET IMPLEMENTED");
    }
    
    //print_r("<br/><h2>Executing Rule:</h2>");
    //print_r($rule);
    //print_r("<br/>");
    //print_r($btx);
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

