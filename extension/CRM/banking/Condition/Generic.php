<?php

class CRM_Banking_Condition_Generic {

  protected $spec;
  
  protected $prob;
  
  public function __construct( $spec ) {
    $this->spec = $spec;
    $this->prob = isset($spec->prob) ? $spec->prob : 100;
  }
  
  
  /**
   * Examine this particular condition. Use $ctx to retrieve and store context items.
   * 
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param CRM_Banking_Matcher_Context $ctx
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $ctx, CRM_Banking_Matcher_Suggestion $suggestion) {
    $suggestion->addEvidence( $this->prob, 'GENERIC REASON' );
    return true;
  }

}