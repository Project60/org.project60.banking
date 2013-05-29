<?php

class CRM_Banking_Matcher_Context {
  
  public $btx;
  
  public function __construct( CRM_Banking_BAO_BankTransaction $btx ) {
    $this->btx = $btx;
  }
}