<?php

/**
 * The generic match plugin is able to do the following :
 * - check the transaction amount to be inside a range
 * - check the transaction date to be inside a range
 * - check the communication using a regex
 */
class CRM_Banking_PluginImpl_Yes extends CRM_Banking_PluginModel_Matcher {

  private $suggestions;

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);
  }

  public function match($btx, $context) {
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->addEvidence( 1.0, "Yes we can" );
    return array($suggestion);
  }

  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($match, $btx) {
    
  }

  function visualize_match($match, $btx) {
    return "Yes !!";
  }

}


