<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
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
 * This class represents the match of a rule (see )
 *  to a banking transaction
 */
class CRM_Banking_Rules_Match {

  protected $rule;        // CRM_Banking_Rules_Rule
  protected $btx;         // CRM_Banking_BAO_BankTransaction
  protected $mapping;     // parameter mapping
  protected $confidence;  // float 0...1

  /**
   * try to match the given bank transaction (btx) against
   * the rule database
   *
   * @param $btx        CRM_Banking_BAO_BankTransaction to be analysed
   * @param $mapping    a value mapping to be applied to the btx data
   * @param $context    CRM_Banking_Matcher_Context     matching context, can be used for caching
   * @param $type       int    rule type to be matched: 1=analyser, 2=matcher
   * @param $confidence float  discard any matches with a confidence below this value
   * @param $rule_id    NULL|int If given, will test a single rule against the btx.
   *
   * @return array a list of CRM_Banking_Rules_Match objects
   */
  public static function matchTransaction($btx, $mapping, $context, $type = 1, $threshold = 0.0, $rule_id=NULL) {
    $data_parsed = self::getMappedData($btx, $mapping);

    $params = [
        'btx_amount'       => CRM_Utils_Array::value('amount',      $data_parsed, 0.00),
        'btx_party_ba_ref' => CRM_Utils_Array::value('_party_IBAN', $data_parsed, ''),
        'btx_ba_ref'       => CRM_Utils_Array::value('_IBAN',       $data_parsed, ''),
        'btx_party_name'   => CRM_Utils_Array::value('name',        $data_parsed, ''),
        'btx_tx_reference' => CRM_Utils_Array::value('reference',   $data_parsed, ''),
        'btx_tx_purpose'   => CRM_Utils_Array::value('purpose',     $data_parsed, ''),
      ];

    // truncate key fields to DB lengths
    CRM_Banking_Rules_Rule::truncateKeyData($params, 'btx_');


    $sql = CRM_Utils_SQL_Select::from('civicrm_bank_rules');
    if ($rule_id === NULL) {
      // Normally we're matching all enabled rules.
      $sql->where('is_enabled');
      $sql->where('valid_until IS NULL OR valid_until > NOW()');
    }
    else {
      // But when testing we need to specify a particular rule. We don't mind
      // if this is enabled or not since we're specifically choosing to test
      // it.
      $sql->where('id = #rule_id');
      $params['rule_id'] = $rule_id;
    }

    // build query based on parameters
    $sql->where('amount_min IS NULL OR amount_min <= #btx_amount');
    $sql->where('amount_max IS NULL OR amount_max >= #btx_amount');
    $sql->where('party_ba_ref IS NULL OR party_ba_ref = @btx_party_ba_ref');
    $sql->where('ba_ref IS NULL OR ba_ref = @btx_ba_ref');
    $sql->where('party_name IS NULL OR party_name = @btx_party_name');
    $sql->where('tx_reference IS NULL OR tx_reference = @btx_tx_reference');
    $sql->where('tx_purpose IS NULL OR tx_purpose = @btx_tx_purpose');

    // generate SQL
    $sql = $sql->param($params)->toSQL();

    $rules_data = CRM_Core_DAO::executeQuery($sql)->fetchAll();
    $rule_matches = [];

    foreach ($rules_data as $rule_data) {
      $rule = new CRM_Banking_Rules_Rule();
      $rule->setFromArray($rule_data);

      $match = new CRM_Banking_Rules_Match($rule, $btx, $mapping);

      if ($match->ruleConditionsMatch()) {
        $rule_matches[] = $match;
      }
    }
    return $rule_matches;
  }


  /**
   * Do the rule's conditions match?
   *
   * @return bool
   */
  public function ruleConditionsMatch() {
    $data_parsed = self::getMappedData($this->btx, $this->mapping);

    foreach ($this->rule->getConditions() as $field => $match_conditions) {

      if (!isset($match_conditions['full_match'])) {
        throw new Exception("Invalid match condition. Only full_match is implemented at present.");
      }

      // Full string match on data_parsed[$field].
      $value = $match_conditions['full_match'];
      if (
        // If we're not supposed to have a value but we do, it's a fail.
        (empty($value) && !empty($data_parsed[$field]))
        ||
        // If we're supposed to have a value, and either we don't have it or it's not the same, it's a fail.
        (!empty($value) && (empty($data_parsed[$field]) || $value != $data_parsed[$field]))
      ) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
  * constructor for a $rule|$btx match object
   * @param $rule       CRM_Banking_Rules_Rule     matching context, can be used for caching
   * @param $btx        CRM_Banking_BAO_BankTransaction to be analysed
   * @param $confidence float  discard any matches with a confidence below this value
   */
  public function __construct($rule, $btx, $mapping, $confidence = 1.0) {
    $this->rule       = $rule;
    $this->btx        = $btx;
    $this->mapping    = $mapping;
    $this->confidence = $confidence;
  }

  /**
   * The confidence value of this rule match
   */
  public function getConfidence() {
    return $this->confidence;
  }

  /**
   * Get the matched bank transaction
   */
  public function getBankTransaction() {
    return $this->btx;
  }

  /**
   * Get the rule that was matched
   */
  public function getRule() {
    return $this->rule;
  }

  /**
   * This function should execute the rule
   * with the given bank transaction (btx)
   *
   * it should also update the execution info in the suggestion
   *  accordingly, so the changes performed are recorded
   *
   * Remark: if this is a 'analyser style rule', this would only
   *   update the btx data, but not
   *
   * @param $suggestion CRM_Banking_Matcher_Suggestion the suggestion being executed or NULL
   */
  public function execute($suggestion = NULL) {
    // this object should already contain the right btx and rule objects

    // Enrich the data according to the execution instructions.
    $data_parsed = self::getMappedData($this->btx, $this->mapping);

    foreach ($this->rule->getExecution() as $execution) {
      if (!isset($execution['set_param_name'])) {
        throw new Exception("Only set_param_name type execution rules are implemented currently. Missing from rule " . $this->rule->getId());
      }
      // Store the value in the param.
      $data_parsed[$execution['set_param_name']] = $execution['set_param_value'];
    }

    // Update the rule to record that it matched.
    // Note: if the user keeps hitting "Analyse Again" this count will keep increasing.
    $this->rule->recordMatch();

    // Save the enriched $data_parsed array.
    self::writeMappedData($this->btx, $this->mapping, $data_parsed);
  }

  /**
   * Will get a merged and mapped set of all data of the btx
   */
  protected static function getMappedData($btx, $mapping) {
    $data_parsed = $btx->getDataParsed();

    // add native fields
    foreach (CRM_Banking_BAO_BankTransaction::$native_data_fields as $field_name) {
      $data_parsed[$field_name] = $btx->$field_name;
    }

    // apply mapping (if any)
    if (!empty($mapping)) {
      foreach ($mapping as $data_field => $mapped_field) {
        if (isset($data_parsed[$data_field])) {
          $data_parsed[$mapped_field] = $data_parsed[$data_field];
        }
      }
    }

    return $data_parsed;
  }

  /**
   * Will "un-map" the data and write it back to
   * to the btx->data_parsed field
   */
  protected static function writeMappedData($btx, $mapping, $data_parsed) {
    $current_data = $btx->getDataParsed();

    // reset mapped parameters, we don't want to overwrite...
    foreach ($mapping as $data_field => $mapped_field) {
      if (isset($current_data[$mapped_field])) {
        $data_parsed[$data_field] = $current_data[$mapped_field];
      }
    }

    // strip native attributes
    foreach (CRM_Banking_BAO_BankTransaction::$native_data_fields as $field_name) {
      if (isset($data_parsed[$field_name])) {
        unset($data_parsed[$field_name]);
      }
    }

    // write to BTX
    $btx->setDataParsed($data_parsed);
  }
}
