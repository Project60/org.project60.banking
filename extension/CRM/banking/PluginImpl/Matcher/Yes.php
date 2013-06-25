<?php

/**
 * The generic match plugin is able to do the following :
 * - check the transaction amount to be inside a range
 * - check the transaction date to be inside a range
 * - check the communication using a regex
 */
class CRM_Banking_PluginImpl_Matcher_Yes extends CRM_Banking_PluginModel_Matcher {

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

  public function visualize_match(CRM_Banking_Matcher_Suggestion $match, $btx) {
    return '<div style="background-color:gray;text-align:center">
            <font size="+1">
            <font color="#ccff66">Y</font>
            <font color="#84ed42">E</font>
            <font color="#54e12a">S</font>
            <font color="#3cdb1e">!</font>
            <font color="#24d512">!</font>
            <font color="#0ccf06">!</font>
            </font>
            </div>';
  }

}


