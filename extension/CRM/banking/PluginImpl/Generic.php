<?php

/**
 * The generic match plugin is able to do the following :
 * - check the transaction amount to be inside a range
 * - check the transaction date to be inside a range
 * - check the communication using a regex
 */
class CRM_Banking_PluginImpl_Generic extends CRM_Banking_PluginModel_Matcher {

  private $suggestions;

  /**
   * class constructor
   */
  function __construct($config_name) {
    $this->suggestions = array();
    parent::__construct($config_name);
  }

  protected function match($btx, $context) {

    // this section will be refactored to use different conditions, but for now, this is hardcoded
    $suggestion = new CRM_Banking_Matcher_Suggestion($this);

    // amount range
    $low = $this->config['amount']['low'];
    $high = $this->config['amount']['high'];
    $factor = $this->config['amount']['prob'] or 1;
    $amount = $btx->amount;
    if ($low && ($amount >= $low))
      if ($high && ($amount <= $high)) {
        $message = ts('the transaction amount is in the range [ ');
        if ($low)
          $message .= number_format($low, 2);
        $message .= ' - ';
        if ($high)
          $message .= number_format($high, 2);
        $message .= ' ]';
        $suggestion->addEvidence($factor, $message);
      }

    $early = $this->config['value_date']['early'];
    $late = $this->config['value_date']['late'];
    $factor = $this->config['value_date']['prob'] or 1;
    $value_date = strtotime($btx->value_date);
    if (($early != '') && ($value_date >= strtotime($early)))
      if (($late != '') && ($value_date <= strtotime($late))) {
        $message = ts('the transaction value date is in the range [ ');
        if ($early)
          $message .= $early;
        $message .= ' - ';
        if ($late)
          $message .= $late;
        $message .= ' ]';
        $suggestion->addEvidence($factor, $message);
      }

    $regex = $this->config['purpose']['regex'];
    $factor = $this->config['purpose']['prob'] or 1;
    $purpose = $btx->data_parsed['purpose'];
    if (($regex != '') && preg_match("/$regex/", $purpose)) {
      $message = ts('the transaction purpose matches the expression "');
      $message .= htmlentities($regex);
      $suggestion->addEvidence($factor, $message);
    }

    if ($suggestion->getProbability() > 0) {
      $this->addSuggestion( $suggestion );
    }

    // close up
    return empty($this->suggestions) ? null : $this->suggestions;
  }

  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  protected function execute($match, $btx) {
    
  }

  function visualize_match($match, $btx) {
    return "Generic match, dude ...";
  }

}

