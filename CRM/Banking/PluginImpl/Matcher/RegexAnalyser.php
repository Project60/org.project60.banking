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

use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext;

/**
 * This matcher uses regular expressions to extract information from the payment meta information
 */
class CRM_Banking_PluginImpl_Matcher_RegexAnalyser extends CRM_Banking_PluginModel_Analyser {

  /**
   * class constructor
   */
  public function __construct($plugin_dao) {
    parent::__construct($plugin_dao);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->rules)) {
      $config->rules = [];
    }

    // see https://github.com/Project60/org.project60.banking/issues/111
    if (!isset($config->variable_lookup_compatibility)) {
      $config->variable_lookup_compatibility = FALSE;
    }
  }

  /**
   * this matcher does not really create suggestions, but rather enriches the parsed data
   *
   * @throws \CRM_Core_Exception
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    // iterate through all rules
    foreach ($this->_plugin_config->rules as $rule) {
      if (empty($rule->fields)) {
        $fields = ['purpose'];
      }
      else {
        $fields = $rule->fields;
      }

      // replace [[...]] style variables in the pattern
      $pattern = $rule->pattern;
      $variables = $this->getVariableList();
      foreach ($variables as $variable) {
        if (preg_match("#\[\[$variable\]\]#", $pattern)) {
          $value = $this->getVariable($variable);
          $pattern = preg_replace("#\[\[$variable\]\]#", print_r($value, TRUE), $pattern);
        }
      }

      // appy rule to all the fields listed...
      foreach ($fields as $field) {
        $matches = [];
        $fieldData = $this->getValue($field, $btx) ?? '';
        if (!is_scalar($fieldData)) {
          continue;
        }

        // match the pattern on the given field data
        $matchCount = preg_match_all($pattern, (string) $fieldData, $matches);

        // and execute the actions for each match...
        for ($i = 0; $i < $matchCount; $i++) {
          $this->logMessage("Rule '{$rule->pattern}' matched.", 'debug');
          $this->processMatch($matches, $i, $rule, $btx);
        }
      }
    }
  }

  /**
   * execute all the action defined by the rule to the given match
   *
   * @param array<int|string, list<string>> $matchData
   *   Matches of preg_match_all().
   *
   * @throws \CRM_Core_Exception
   */
  private function processMatch(
    array $matchData,
    int $matchIndex,
    \stdClass $rule,
    \CRM_Banking_BAO_BankTransaction $btx
  ): void {
    $matchContext = new RegexAnalyserMatchContext($matchData, $matchIndex, $rule, $this, $btx, $this->logger);
    /** @var \Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface $actionHandler */
    $actionHandler = Civi::service(RegexAnalyserActionHandlerInterface::class);
    foreach ($rule->actions as $action) {
      $actionHandler->execute($action, $matchContext);
    }
  }

  /**
   * Get the value either from $data_parsed, or the propagation value.
   */
  private function getValue(string $key, \CRM_Banking_BAO_BankTransaction $btx): mixed {
    $dataParsed = $btx->getDataParsed();
    // see https://github.com/Project60/org.project60.banking/issues/111
    $lookupCompatibility = $this->_plugin_config->variable_lookup_compatibility;
    // @phpstan-ignore empty.notAllowed
    if (!empty($dataParsed[$key]) || (!$lookupCompatibility && isset($dataParsed[$key]))) {
      return $dataParsed[$key];
    }
    else {
      // try value propagation
      $value = $this->getPropagationValue($btx, NULL, $key);
      if (NULL === $value) {
        $this->logMessage("RegexAnalyser - Cannot find source '$key' for rule or filter.", 'debug');
      }

      return NULL;
    }
  }

}
