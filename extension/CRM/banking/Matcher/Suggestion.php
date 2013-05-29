<?php

class CRM_Banking_Matcher_Suggestion {

  private $_probability;
  private $_reasons;
  private $_btx;
  private $_plugin;

  public function __construct($blob, $btx) {
    $this->_probability = 0;
    $this->_reasons = array();
    $this->_reasons = array();
  }

  public function getProbability() {
    return $this->_probability;
  }

  /**
   * addEvidence computes the Bayesian combined evidence
   */
  public function addEvidence( $factor, $message = '') {
    if (($factor < 0) or ($factor > 1)) {
      CRM_Core_Session::setStatus(ts('Cannot add evidence outside [0,1] range, assuming 1'), ts('Warning: bad matcher evidence'), 'alert');
      $factor = 1;
    }

    $this->_probability = $this->_probability + (1 - $this->_probability) * $factor;
    if ($message) $this->messages[] = $message;
    
  }

  public function execute(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_PluginModel_Matcher $plugin = null) {
    // if plugin is not supplied (by the matcher engine), recreate it
    // perform execute
    $continue = $plugin->execute($this, $btx);

    return $continue;
  }

}