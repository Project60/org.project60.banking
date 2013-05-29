<?php
/**
 * The generic match plugin is able to do the following :
 * - check the transaction amount to be inside a range
 * - check the transaction date to be inside a range
 * - check the communication using a regex
 */
class CRM_Banking_PluginImpl_Generic extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);
  }

  protected function match($btx, $context) {
    // create an empty suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this);

    ///...
    
    // close up
    if ($this->probability > 0) // change
      return $suggestion;
    return null;
  }

  protected function execute($match, $btx) {
    
  }

  function visualize_match($match, $btx) {
    return "Generic match, dude ...";
  }

}

