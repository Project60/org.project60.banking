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
  protected $confidence;  // float 0...1

  /**
   * try to match the given bank transaction (btx) against
   * the rule database
   *
   * @param $btx        CRM_Banking_BAO_BankTransaction to be analysed
   * @param $context    CRM_Banking_Matcher_Context     matching context, can be used for caching
   * @param $type       int    rule type to be matched: 1=analyser, 2=matcher
   * @param $confidence float  discard any matches with a confidence below this value
   *
   * @return array a list of CRM_Banking_Rules_Match objects
   */
  public static function matchTransaction($btx, $context, $type = 1, $threshold = 0.0) {
    $data_parsed = $btx->getDataParsed();

    $sql = CRM_Utils_SQL_Select::from('civicrm_bank_rules')
      ->where('is_enabled')
      ->where('(amount_min IS NULL OR amount_min <= #btx_amount)')
      ->where('(amount_max IS NULL OR amount_max >= #btx_amount)')
      ->where('(party_ba_ref IS NULL OR party_ba_ref = @btx_party_ba_ref)')
      ->where('(party_name IS NULL OR party_name = @btx_party_name)')
      ->where('(tx_reference IS NULL OR tx_reference = @btx_tx_reference)')
      ->where('(tx_purpose IS NULL OR tx_purpose = @btx_tx_purpose)')
      ->param([
        'btx_amount'       => $data_parsed['amount_parsed'],
        'btx_party_ba_ref' => $data_parsed['_party_IBAN'],
        'btx_party_name'   => $data_parsed['name'],
        'btx_tx_reference' => $data_parsed['reference'],
        'btx_tx_purpose'   => $data_parsed['purpose'],
      ])
      ->toSQL();

    $rules_data = CRM_Core_DAO::executeQuery($sql)->fetchAll();
    $rule_matches = [];

    foreach ($rules_data as $rule_data) {
      $rule = new CRM_Banking_Rules_Rule();
      $rule->setFromArray($rule_data);

      $match = new static($rule, $btx);

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
    $data_parsed = $this->btx->getDataParsed();
    foreach ($this->rule->getConditions() as $field => $value) {
      // For now we just look in data_parsed.
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
  public function __construct($rule, $btx, $confidence = 1.0) {
    $this->rule       = $rule;
    $this->btx        = $btx;
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
    $data_parsed = $this->btx->getDataParsed();
    foreach ($this->rule->getExecution() as $f=>$v) {
      $data_parsed[$f] = $v;
    }

    // Update the rule to record that it matched.
    // Note: if the user keeps hitting "Analyse Again" this count will keep increasing.
    $this->rule->recordMatch();

    // Save the enriched $data_parsed array.
    $this->btx->setDataParsed($data_parsed);
  }
}
