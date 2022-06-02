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

require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher use regular expressions to extract information from the payment meta information
 */
class CRM_Banking_PluginImpl_Matcher_RegexAnalyser extends CRM_Banking_PluginModel_Analyser {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->rules)) $config->rules = array();

    // see https://github.com/Project60/org.project60.banking/issues/111
    if (!isset($config->variable_lookup_compatibility))   $config->variable_lookup_compatibility = FALSE;
  }

  /**
   * this matcher does not really create suggestions, but rather enriches the parsed data
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {    $config = $this->_plugin_config;
    $data_parsed = $btx->getDataParsed();

    // itreate trough all rules
    foreach ($this->_plugin_config->rules as $rule) {
      if (empty($rule->fields)) {
        $fields = array('purpose');
      } else {
        $fields = $rule->fields;
      }

      // replace [[...]] style variables in the pattern
      $pattern = $rule->pattern;
      $variables = $this->getVariableList();
      foreach ($variables as $variable) {
        if (preg_match("#\[\[$variable\]\]#", $pattern)) {
          $value = $this->getVariable($variable);
          $pattern = preg_replace("#\[\[$variable\]\]#", print_r($value,1), $pattern);
        }
      }

      // appy rule to all the fields listed...
      foreach ($fields as $field) {
        $matches = array();
        $field_data = $this->getValue($field, NULL, NULL, $data_parsed, $btx);

        // match the pattern on the given field data
        $match_count = preg_match_all($pattern, $field_data, $matches);

        // and execute the actions for each match...
        for ($i=0; $i < $match_count; $i++) {
          $this->logMessage("Rule '{$rule->pattern}' matched.", 'debug');
          $this->processMatch($matches, $i, $data_parsed, $rule, $btx);
        }
      }
    }

    // save changes and that's it
    $btx->setDataParsed($data_parsed);
  }

  /**
   * execute all the action defined by the rule to the given match
   */
  function processMatch($match_data, $match_index, &$data_parsed, $rule, $btx) {
    foreach ($rule->actions as $action) {
      if ($action->action=='copy') {
        // COPY value from match group to parsed data
        $data_parsed[$action->to] = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);

      } elseif ($action->action=='copy_append') {
        // COPY value, but append to the target
        $data_parsed[$action->to] .= $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);

      } elseif ($action->action=='copy_ltrim_zeros') {
        // COPY value, but remove leading zeros
        $data_parsed[$action->to] = ltrim($this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx), '0');

      } elseif ($action->action=='set') {
        // SET value regardless of the match context
        $data_parsed[$action->to] = $action->value;

      } elseif ($action->action=='align_date') {
        // ALIGN a date forwards or backwards
        $value = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);
        $data_parsed[$action->to] = CRM_Utils_BankingToolbox::alignDateTime($value, $rule->offset, $rule->skip);

      } elseif ($action->action=='unset') {
        // UNSET a certain value
        unset($data_parsed[$action->to]);

      } elseif ($action->action=='strtolower') {
        // data to lowercase
        $data_parsed[$action->to] = strtolower($this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx));

      } elseif ($action->action=='sha1') {
        // reduce to SHA1 checksum
        $data_parsed[$action->to] = sha1($this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx));

      } elseif (substr($action->action, 0, 7) =='sprint:') {
        // format data
        $data   = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);
        $format = substr($action->action, 7);
        $data_parsed[$action->to] = sprintf($format, $data);

      } elseif ($action->action=='preg_replace') {
        // perform preg_replace
        if (empty($action->search_pattern) || !isset($action->replace)) {
          error_log("org.project60.banking bad 'preg_replace' spec in plugin {$this->_plugin_id}.");
        } else {
          $subject = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);
          $data_parsed[$action->to] = preg_replace($action->search_pattern, $action->replace, $subject);
        }

      } elseif ($action->action=='calculate') {
        // CALCULATE the new value with an php expression, using {}-based tokens
        $expression = $action->from;
        $matches = array();
        while (preg_match('#(?P<variable>{[^}]+})#', $expression, $matches)) {
          // replace variable with value
          $token = trim($matches[0], '{}');
          $value = $this->getValue($token, $match_data, $match_index, $data_parsed, $btx);
          $expression = preg_replace('#(?P<variable>{[^}]+})#', $value, $expression, 1);
        }
        $data_parsed[$action->to] = eval("return $expression;");

      } elseif ($action->action=='map') {
        // MAP a value given a list of replacements
        $value = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);
        if (isset($action->mapping->$value)) {
          $data_parsed[$action->to] = $action->mapping->$value;
        // DISABLED WARNINGS } else {
        //  error_log("org.project60.banking: RegexAnalyser - incomplete mapping: '".$action->action."'");
        }

      } elseif (substr($action->action, 0, 7) =='lookup:') {
        // LOOK UP values via API::getsingle
        //   parameters are in format: "EntityName,result_field,lookup_field"
        $params = explode(',', substr($action->action, 7));
        $value = $this->getValue($action->from, $match_data, $match_index, $data_parsed, $btx);
        $query = array($params[2] => $value, 'version' => 3, 'return' => $params[1]);
        if (!empty($action->parameters)) {
          foreach($action->parameters as $key => $value) {
            $query[$key] = $value;
          }
        }

        // execute and log
        $this->logger->setTimer('regex:lookup');

        $this->logMessage("Calling API {$params[0]}.getsingle: " . json_encode($query), 'debug');
        $result = $this->executeAPIQuery($params[0], 'getsingle', $query, $action);
        $this->logMessage("API result: " . json_encode($result), 'debug');
        $this->logTime("API {$params[0]}.getsingle", 'regex:lookup');

        if (empty($result['is_error'])) {
          // something was found... copy value
          $data_parsed[$action->to] = $result[$params[1]];
        }

      } elseif (substr($action->action, 0, 4) =='api:') {
        /**
         * Look up parameters via API call
         *  the 'action' format is: "<entity>:<action>:<result_field>[:multiple]"
         *     EntityName    the CiviCRM API entity
         *     action        the CiviCRM API action
         *     result_field  the field to take from the result
         *     multiple      if this is given, multiple results will be added to the field, separated by comma
         *                     otherwise the result will only be copied if exactly one match was found
         *
         * further attributes can be given as follows:
         *  const_<param>    set the API parameter to a constant, e.g. const_contact_type = 'Individual'
         *  param_<param>    set the API parameter to the value of another field, e.g. const_first_name = 'first_name'
         */
        // compile query
        $params = explode(':', substr($action->action, 4));
        $query = array('return' => $params[2]);
        foreach ($action as $key => $value) {
          if (substr($key, 0, 6) =='const_') {
            $query[substr($key, 6)] = $value;
          } elseif (substr($key, 0, 6) =='param_') {
            $query[substr($key, 6)] = $this->getValue($value, $match_data, $match_index, $data_parsed, $btx);
          } elseif (substr($key, 0, 10) =='jsonparam_') {
            $query[substr($key, 10)] = json_decode($this->getValue($value, $match_data, $match_index, $data_parsed, $btx), TRUE);
          } elseif (substr($key, 0, 10) =='jsonconst_') {
            $query[substr($key, 10)] = json_decode($value, TRUE);
          }
        }

        // execute query
        try {
          $this->logger->setTimer('regex:api');
          $this->logMessage("Calling API {$params[0]}.{$params[1]}: " . json_encode($query), 'debug');
          $result = $this->executeAPIQuery($params[0], $params[1], $query, $action);
          $this->logMessage("API result: " . json_encode($result), 'debug');
          $this->logTime("API {$params[0]}.{$params[1]}", 'regex:api');

          if (isset($params[3]) && $params[3]=='multiple') {
            // multiple values allowed
            $results = array();
            foreach ($result['values'] as $entity) {
              $results[] = $entity[$params[2]];
            }
            $data_parsed[$action->to] = implode(',', $results);

          } else {
            // only valid if it's the only value
            if ($result['count'] == 1) {
              $entity = reset($result['values']);
              $data_parsed[$action->to] = $entity[$params[2]];
            }
          }
        } catch (Exception $e) {
          // TODO: this didn't work... how can we do this?
          $this->logMessage("Exception in API {$params[0]}.{$params[1]}: " . $e->getMessage(), 'debug');
        }

      } else {
        error_log("org.project60.banking: RegexAnalyser - bad action: '".$action->action."'");
      }
    }
  }

  /**
   * Get the value either from the match context, or the already stored data
   */
  protected function getValue($key, $match_data, $match_index, $data_parsed, $btx = NULL) {
    // see https://github.com/Project60/org.project60.banking/issues/111
    if ($this->_plugin_config->variable_lookup_compatibility) {
      if (!empty($match_data[$key][$match_index])) {
        return $match_data[$key][$match_index];
      } else if (!empty($data_parsed[$key])) {
        return $data_parsed[$key];
      } else {
        // try value propagation
        $value = $this->getPropagationValue($btx, NULL, $key);
        if ($value) {
          return $value;
        } else {
          $this->logMessage("RexgexAnalyser - Cannot find source '$key' for rule or filter.", 'debug');
          return '';
        }
      }
    } else {
      if (isset($match_data[$key][$match_index])) {
        return $match_data[$key][$match_index];
      } else if (isset($data_parsed[$key])) {
        return $data_parsed[$key];
      } else {
        // try value propagation
        $value = $this->getPropagationValue($btx, NULL, $key);
        if ($value) {
          return $value;
        } else {
          $this->logMessage("RexgexAnalyser - Cannot find source '$key' for rule or filter.", 'debug');
          return '';
        }
      }
    }
  }

  /**
   * execute API Query
   */
  protected function executeAPIQuery($entity, $command, $query, $action) {
    $command = strtolower($command);
    if (empty($action->sql) || !$action->sql || !in_array($command, array('get', 'getsingle'))) {
      // execute via API
      $query['version'] = 3;
      return civicrm_api($entity, $command, $query);

    } else  {
      // execute via SQL
      // compile select
      if (empty($query['return'])) {
        $select_clause = '*';
      } else {
        $select_clause = $query['return'];
      }

      // compile from
      $from = $this->getTableName($entity);

      // compile where
      $where_clauses = array();
      $query_params = array();
      foreach ($query as $key => $value) {
        if (!in_array($key, array('return', 'sort', 'limit', 'option', 'version'))) {
          // TODO: support for sort, limit, etc.
          if (is_array($value)) {
            // TODO: support for LIKE, IN, etc.
          } else {
            $index = count($query_params) + 1;
            $where_clauses[] = "`$key` = %$index";
            $query_params[$index] = array($value, 'String');
          }
        }
      }
      if (empty($where_clauses)) {
        $where_clause = 'TRUE';
      } else {
        $where_clause = '(' . implode(') AND (', $where_clauses) . ')';
      }

      // should there be a limit
      if ($command == 'getsingle') {
        $limit = 'LIMIT 1';
      } elseif ($query['limit']) {
        $limit = "LIMIT {$query['limit']}";
      } else {
        $limit = '';
      }

      // execute the query
      $dao_query = CRM_Core_DAO::executeQuery("SELECT {$select_clause} FROM {$from} WHERE {$where_clause} {$limit};", $query_params);
      if ($command == 'getsingle') {
        if ($dao_query->fetch()) {
          return $dao_query->toArray();
        } else {
          return civicrm_api3_create_error("Not found");
        }
      } else { // $command == 'get'
        $results = array();
        while ($dao_query->fetch()) {
          $results[] = $dao_query->toArray();
        }
        return civicrm_api3_create_success($results);
      }
    }
  }

  /**
   * get the CiviCRM table name for an entity
   */
  protected function getTableName($entity) {
    // from: https://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case#1993772
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $entity, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
      $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return 'civicrm_' . implode('_', $ret);
  }

}
