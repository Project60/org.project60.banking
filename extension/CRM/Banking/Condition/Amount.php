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
 * Matches btx.amount
 * 
 * Options for amount ubconditions :
 *    <op> NNN          where op is lt, le, gt, ge, ed, ne
 *    positive
 *    negative
 *    multipleOf NNN
 *
 */
class CRM_Banking_Condition_Amount extends CRM_Banking_Condition_Generic {
  
  public function __construct( $spec ) {
    parent::__construct( $spec );
  }
  
  

}