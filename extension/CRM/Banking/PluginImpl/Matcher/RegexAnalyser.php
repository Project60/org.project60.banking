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
        if (isset($data_parsed[$field])) {
          $field_data = $data_parsed[$field];
          $matches = array();

          // match the pattern on the given field data
          $match_count = preg_match_all($pattern, $field_data, $matches);

          // and execute the actions for each match...
          for ($i=0; $i < $match_count; $i++) {
            $this->processMatch($matches, $i, $data_parsed, $rule);
          }
        }
      }
    }

    // save changes and that's it
    $btx->setDataParsed($data_parsed);
  }

  /** 
   * execute all the action defined by the rule to the given match
   */
  function processMatch($match_data, $match_index, &$data_parsed, $rule) {
    foreach ($rule->actions as $action) {
      if ($action->action=='copy') {
        // COPY value from match group to parsed data
        $data_parsed[$action->to] = $this->getValue($action->from, $match_data, $match_index, $data_parsed);

      } elseif ($action->action=='copy_append') {
        // COPY value, but append to the target
        $data_parsed[$action->to] .= $this->getValue($action->from, $match_data, $match_index, $data_parsed);

      } elseif ($action->action=='copy_ltrim_zeros') {
        // COPY value, but remove leading zeros
        $data_parsed[$action->to] = ltrim($this->getValue($action->from, $match_data, $match_index, $data_parsed), '0');

      } elseif ($action->action=='set') {
        // SET value regardless of the match context
        $data_parsed[$action->to] = $action->value;

      } elseif ($action->action=='calculate') {
        // CALCULATE the new value with an php expression, using {}-based tokens
        $expression = $action->from;
        $matches = array();
        while (preg_match('#(?P<variable>{[^}]+})#', $expression, $matches)) {
          // replace variable with value
          $token = trim($matches[0], '{}');
          $value = $this->getValue($token, $match_data, $match_index, $data_parsed);
          $expression = preg_replace('#(?P<variable>{[^}]+})#', $value, $expression, 1);
        }
        $data_parsed[$action->to] = eval("return $expression;");

      } elseif ($action->action=='map') {
        // MAP a value given a list of replacements
        $value = $this->getValue($action->from, $match_data, $match_index, $data_parsed);
        if (isset($action->mapping->$value)) {
          $data_parsed[$action->to] = $action->mapping->$value;
        // DISABLED WARNINGS } else {
        //  error_log("org.project60.banking: RegexAnalyser - incomplete mapping: '".$action->action."'");
        }

      } elseif (substr($action->action, 0, 7) =='lookup:') {
        // LOOK UP values via API::getsingle
        //   parameters are in format: "EntityName,result_field,lookup_field"
        $params = split(',', substr($action->action, 7));
        $value = $this->getValue($action->from, $match_data, $match_index, $data_parsed);
        $query = array($params[2] => $value, 'version' => 3);
        if (!empty($action->parameters)) {
          foreach($action->parameters as $key => $value) {
            $query[$key] = $value;
          }
        }
        $result = civicrm_api($params[0], 'getsingle', $query);
        if (empty($result['is_error'])) {
          // something was found... copy value
          $data_parsed[$action->to] = $result[$params[1]];
        }
      } else {
        error_log("org.project60.banking: RegexAnalyser - bad action: '".$action->action."'");
      }
    }
  }

  /**
   * Get the value either from the match context, or the already stored data
   */
  protected function getValue($key, $match_data, $match_index, $data_parsed) {
    if (!empty($match_data[$key][$match_index])) {
      return $match_data[$key][$match_index];
    } else if (!empty($data_parsed[$key])) {
      return $data_parsed[$key];
    } else {
      error_log("org.project60.banking: RexgexAnalyser - Cannot find source '$key' for rule or filter.");
      return '';
    }
  }  
}


