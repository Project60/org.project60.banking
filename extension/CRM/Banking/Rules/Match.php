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
   * @param $confidence float  discard any matches with a confidence below this value
   *
   * @return array a list of CRM_Banking_Rules_Match objects
   */
  public static function matchTransaction($btx, $context, $threshold = 0.0) {
    // TODO: implement
    return array();
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
   * @param $suggestion CRM_Banking_Matcher_Suggestion the suggestion being executed
   */
  public function execute($suggestion) {
    // TODO
    // this object should already contain the right btx and rule objects
  }
}