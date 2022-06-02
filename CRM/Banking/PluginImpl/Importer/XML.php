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

use CRM_Banking_ExtensionUtil as E;

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_Importer_XML extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->defaults))      $config->defaults      = array();
    if (!isset($config->payments))      $config->payments      = array();
    if (!isset($config->payment_lines)) $config->payment_lines = array();
  }

  /**
   * parsed XML document
   * @var DOMDocument
   */
  protected $document = NULL;

  /**
   * XPath query engine on the current path
   * @var DOMXPath
   */
  protected $xpath = NULL;

  /**
   * This will be used to suppress duplicates within the same statement
   *  when automatically generating references
   */
  protected $bank_reference_cache = array();

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  static function displayName()
  {
    return 'XML Importer';
  }

  /**
   * will parse and init the XML document access structure
   */
  function initDocument($file_path, $params ) {
    // TODO: Error handling
    if ($this->document && $this->document->documentURI == $file_path) return;

    // document not yet parsed => do it
    $config = $this->_plugin_config;
    $this->document = new DOMDocument();
    $this->document->Load($file_path);
    $this->xpath = new DOMXPath($this->document);

    foreach ($config->namespaces as $ref => $ns) {
      $this->xpath->registerNamespace($ref, $ns);
    }
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
    $this->reportDone(E::ts("Importing streams not supported by this plugin."));
  }

  /**
   * Test if the given file can be imported
   *
   * @var
   * @return TODO: data format?
   */
  function probe_file( $file_path, $params )
  {
    $this->initDocument($file_path, $params);
    // TODO: error handling

    if (isset($this->_plugin_config->probe)) {
      $value = $this->xpath->query($this->_plugin_config->probe);
      if (get_class($value)=='DOMNodeList') {
        return $value->length > 0;
      } else {
        return !empty($value);
      }
    } else {
      // no probe string set -> done.
      return true;
    }
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
    $this->initDocument($file_path, $params);

    if (empty($config->stmt_path)) {
      // not stmt_path => the whole document is assumed one statement

      // first: set the count
      $params['total_tx_count'] = 0;
      foreach ($config->payments as $payment_spec) {
        $params['total_tx_count'] += $this->xpath->query($payment_spec->path)->length;
      }
      foreach ($config->payment_lines as $payment_spec) {
        $params['total_tx_count'] += $this->xpath->query($payment_spec->path)->length;
      }

      // then: run the importer
      $this->importStatement($params);

    } else {
      // stmt_path set => there are multiple statements in here
      $statements = $this->xpath->query($config->stmt_path);

      // first: set the count
      $params['total_tx_count'] = 0;
      foreach ($statements as $statement) {
        foreach ($config->payments as $payment_spec) {
          $params['total_tx_count'] += $this->xpath->query($payment_spec->path, $statement)->length;
        }
        foreach ($config->payment_lines as $payment_spec) {
          $params['total_tx_count'] += $this->xpath->query($payment_spec->path, $statement)->length;
        }
      }

      // then: run the importer
      $index = 0;
      foreach ($statements as $statement) {
        $this->importStatement($params, $index, $statement);
      }
    }

    $this->reportDone();
  }

  /**
   * Import the given statement
   *
   * @param $params       array    parameters
   * @param $index        int      index of last transaction imported
   * @param $context_node DOMNode
   */
  protected function importStatement($params, &$index = 0, $context_node = NULL) {
    $config = $this->_plugin_config;
    $this->logMessage("Starting new batch.", 'debug');
    $batch = $this->openTransactionBatch();

    // execute rules for statement
    $data =[];
    foreach ($config->rules as $rule) {
      $this->apply_rule($rule, $context_node, $data);
    }

    // collect payment identifier specs
    //  $config->payments is a single, deprecated spec
    $payment_specs = array();
    if (!empty($config->payments)) {
      $payment_specs[] = $config->payments;
    }
    foreach ($config->payment_lines as $payment_line) {
      $payment_specs[] = $payment_line;
    }

    foreach ($payment_specs as $payment_spec) {
      // compile filter list
      if (!empty($payment_spec->filters)) {
        $filters = $payment_spec->filters;
      } else {
        $filters = array();
      }
      if (!empty($payment_spec->filter)) {
        $filters[] = $payment_spec->filter;
      }

      // iterate nodes
      $payments = $this->xpath->query($payment_spec->path, $context_node);
      foreach ($payments as $payment_node) {
        $index += 1;

        // evaluate filters
        $node_accepted = TRUE;
        foreach ($filters as $filter) {
          $filter_maches = $this->filterMatches($payment_node, $filter);
          if ($filter_maches) {
            $node_accepted = FALSE;
            break;
          }
        }
        if (!$node_accepted) continue;

        // import the line
        $this->import_payment($payment_spec, $payment_node, $data, $index, $params);
      }
    }

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
  }

  /**
   * see if the filter matches
   *
   * @return TRUE if the expression is true for the payment node
   */
  protected function filterMatches($payment_node, $filter) {
    if ('exists:' == substr($filter, 0, 7)) {
      $filter_result = $this->xpath->evaluate(substr($filter, 7), $payment_node);
      if ($filter_result->length == 0) {
        return TRUE;
      }

    } elseif ('not_exists:' == substr($filter, 0, 11)) {
      $filter_result = $this->xpath->evaluate(substr($filter, 11), $payment_node);
      if ($filter_result->length > 0) {
        return TRUE;
      }

    } else {
      // unknown filter spec

    }
    return FALSE;
  }

  /**
   * Processes and imports one individual payment node
   */
  protected function import_payment($payment_spec, $payment_node, $stmt_data, $index, $params) {
    $config = $this->_plugin_config;
    $progress = ((float)$index / (float) $params['total_tx_count']);

    $raw_data = $payment_node->ownerDocument->saveXML($payment_node);
    $raw_data = preg_replace("/>\s+</", "><", $raw_data);      // 'flatten' raw_data

    $data = [
        'version'   => 3,
        'currency'  => 'EUR',
        'type_id'   => 0,
        'status_id' => 0,
        'data_raw'  => $raw_data,
        'sequence'  => $index,
    ];

    // set default values from config:
    foreach ($config->defaults as $key => $value) {
      $data[$key] = $value;
    }

    // copy tx. prefixed payment data
    foreach ($stmt_data as $key => $value) {
      if ($this->startsWith($key, 'tx.')) {
        $data[substr($key, 3)] = $value;
      }
    }

    // now apply the rules
    foreach ($payment_spec->rules as $rule) {
      $this->apply_rule($rule, $payment_node, $data);
    }

    // look up the bank accounts
    $this->lookupBankAccounts($data);

    // do some post processing
    if (!isset($config->bank_reference)) {
      // set MD5 hash as unique reference
      if (isset($stmt_data['tx_reference_seed'])) {
        $bank_reference = md5($raw_data . $stmt_data['tx_reference_seed']);
      } else {
        $bank_reference = md5($raw_data . json_encode($data));
      }

      if (!isset($this->bank_reference_cache[$bank_reference])) {
        // this is a new reference (within this bank statement)
        $this->bank_reference_cache[$bank_reference] = 1;
      } else {
        // this reference already exists => append number
        $counter = $this->bank_reference_cache[$bank_reference];
        $this->bank_reference_cache[$bank_reference] = $counter + 1;
        $bank_reference = md5($bank_reference . $counter);
      }
      $data['bank_reference'] = $bank_reference;

    } else {
      // otherwise use the template
      $bank_reference = $config->bank_reference;
      $tokens = array();
      preg_match('/\{([^\}]+)\}/', $bank_reference, $tokens);
      foreach ($tokens as $key => $token_name) {
        if (!$key) continue;  // match#0 is not relevant
        $token_value = isset($data[$token_name])?$data[$token_name]:'';
        $bank_reference = str_replace("{{$token_name}}", $token_value, $bank_reference);
      }
      $data['bank_reference'] = $bank_reference;
    }

    // prepare $btx: put all entries, that are not for the basic object, into parsed data
    $btx_parsed_data = array();
    foreach ($data as $key => $value) {
      if (!in_array($key, $this->_primary_btx_fields)) {
        // this entry has to be moved to the $btx_parsed_data records
        $btx_parsed_data[$key] = $value;
        unset($data[$key]);
      }
    }
    $data['data_parsed'] = json_encode($btx_parsed_data);

    // and finally write it into the DB
    $duplicate = $this->checkAndStoreBTX($data, $progress, $params);

    $this->reportProgress($progress, sprintf("Imported transaction #%d", $index));
  }

  /**
   * executes an import rule
   */
  protected function apply_rule($rule, $context, &$data) {
    // evaluate the condition (if present)
    $this->logMessage("Applying rule: " . json_encode($rule), 'debug');
    if (!$this->checkCondition($rule, $context, $data)) {
      return;
    }

    // get value
    $value = NULL;
    if (isset($rule->from)) {
      $value = $this->getValue($rule->from, $data, $context);
    }

    // execute the rule
    if ($this->startsWith($rule->type, 'set')) {
      // SET is a simple copy command:
      $value = $this->getValue($rule->from, $data, $context);
      $data[$rule->to] = $value;

    } elseif ($this->startsWith($rule->type, 'append')) {
      // APPEND appends the string to a give value
      $value = $this->getValue($rule->from, $data, $context);
      if (!isset($data[$rule->to])) $data[$rule->to] = '';
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user defined a concat string
        $data[$rule->to] = $data[$rule->to].$params[1].$value;
      } else {
        // default concat string is " "
        $data[$rule->to] = $data[$rule->to]." ".$value;
      }

    } elseif ($this->startsWith($rule->type, 'trim')) {
      // TRIM will strip the string of
      $value = $this->getValue($rule->from, $data, $context);
      $params = explode(":", $rule->type);
      if (isset($params[1])) {
        // the user provided a the trim parameters
        $data[$rule->to] = trim($value, $params[1]);
      } else {
        $data[$rule->to] = trim($value);
      }

    } elseif ($this->startsWith($rule->type, 'unset')) {
      // UNSET will remove temporary variables
      unset($data[$rule->to]);

    } elseif ($this->startsWith($rule->type, 'replace')) {
      // REPLACE will replace a substring
      $value = $this->getValue($rule->from, $data, $context);
      $params = explode(":", $rule->type);
      $data[$rule->to] = str_replace($params[1], $params[2], $value);

    } elseif ($this->startsWith($rule->type, 'format')) {
      // will use the sprintf format
      $value = $this->getValue($rule->from, $data, $context);
      $params = explode(":", $rule->type);
      $data[$rule->to] = sprintf($params[1], $value);

    } elseif ($this->startsWith($rule->type, 'constant')) {
      // will just set a constant string
      $data[$rule->to] = $rule->from;

    } elseif ($this->startsWith($rule->type, 'align_date')) {
      // ALIGN a date forwards or backwards
      $params = explode(":", $rule->type, 2);
      $offset = ($params[1] == 'backward') ? "-1 day" : "+1 day";
      $btx[$rule->to] = CRM_Utils_BankingToolbox::alignDateTime($value, $offset, explode(',', $rule->skip));

    } elseif ($this->startsWith($rule->type, 'strtotime')) {
      // STRTOTIME is a date parser
      $value = $this->getValue($rule->from, $data, $context);
      $params = explode(":", $rule->type, 2);
      if (isset($params[1])) {
        // the user provided a date format
        $datetime = DateTime::createFromFormat($params[1], $value);
        if ($datetime) {
          $data[$rule->to] = $datetime->format('YmdHis');
        }
      } else {
        $data[$rule->to] = date('YmdHis', strtotime($value));
      }

    } elseif ($this->startsWith($rule->type, 'amount')) {
      // AMOUNT will take care of currency issues, like "," instead of "."
      $value = $this->getValue($rule->from, $data, $context);
      $data[$rule->to] = str_replace(",", ".", $value);

    } elseif ($this->startsWith($rule->type, 'regex:')) {
      // REGEX will extract certain values from the line
      $value = $this->getValue($rule->from, $data, $context);
      $pattern = substr($rule->type, 6);
      $matches = array();
      if (preg_match($pattern, $value, $matches)) {
        // we found it!
        $data[$rule->to] = $matches[1];
      } else {
        if (!empty($rule->warn)) {
          $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE,
            sprintf(E::ts("Pattern '%s' was not found in entry '%s'."), $pattern, $value));
        }
      }

    } else {
      error_log("org.project60.banking XMLImporter: rule type '{$rule->type}' unknown.");
    }
  }

  /**
   * Extract the value for the given key from the resources (line, btx).
   */
  protected function getValue($key, $data, $context = NULL) {
    // get value
    if ($this->startsWith($key, '_constant:')) {
      return substr($key, 10);
    } elseif ($this->startsWith($key, 'xpath:')) {
      $path = substr($key, 6);
      $result = $this->xpath->evaluate($path, $context);
      if (get_class($result)=='DOMNode') {
        return $result->nodeValue;
      } elseif (get_class($result)=='DOMNodeList') {
        $value = '';
        foreach ($result as $node) {
          $value .= $node->nodeValue;
        }
        return $value;
      } else {
        return $result;
      }
    } elseif (isset($data[$key])) {
      return $data[$key];
    } else {
      error_log("org.project60.banking: XMLImporter - Cannot find source '$key' for rule or filter.");
      return '';
    }
  }

  /**
   * Test the rule->if condition.
   */
  protected function checkCondition($rule, $context, $data) {
    if (empty($rule->if)) return TRUE;
    $pattern = '#^(?P<term1>[\w:.]+) +(?P<op>=|!=|<|>|>=|<=|IN|!IN) +(?P<term2>[\w:.]+)$#';
    if (preg_match($pattern, $rule->if, $matches)) {
      $term1 = $this->getValue($matches['term1'], $data, $context);
      $term2 = $this->getValue($matches['term2'], $data, $context);
      switch ($matches['op']) {
        case '=':
          return $term1 == $term2;
        case '!=':
          return $term1 != $term2;
        case '<':
          return $term1 < $term2;
        case '>':
          return $term1 > $term2;
        case '<=':
          return $term1 <= $term2;
        case '>=':
          return $term1 >= $term2;
        case 'IN':
          return strstr($term2, $term1);
        case '!IN':
          return !strstr($term2, $term1);
        default:
          // invalid rule
          return TRUE;
      }
    } else {
      // invalid rule
      return TRUE;
    }
  }
}

