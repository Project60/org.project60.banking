<?php
/**
 * Matches btx.amount
 * 
 * Options for amount ubconditions :
 *    <op> NNN          where op is lt, le, gt, ge, ed, ne
 *    positive
 *    negative
 *    multipleOf NNN
 */

class CRM_Banking_Condition_Amount extends CRM_Banking_Condition_Generic {
  
  public function __construct( $spec ) {
    parent::__construct( $spec );
  }
  
  

}