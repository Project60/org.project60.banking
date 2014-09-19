<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 P. Delbar                      |
| Author: P. Delbar                                      |
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
 * @deprecated ?
 */
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