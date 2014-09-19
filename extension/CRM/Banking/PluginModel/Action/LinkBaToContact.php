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
class CRM_Banking_PluginModel_Action_LinkBaToContact {

  public function describe($params, $btx) {
//    $ftp = new CRM_Financial_BAO_FinancialType();
//    $ftp->get($params->financial_type_id);
    return "registering that this bank account belongs to this Contact";
  }

  public function execute($params, $btx, CRM_Banking_Matcher_Suggestion $match) {
    $contact_id = $match->getParameter('contact_id');
    if ($contact_id) {
      $baid = $btx->party_ba_id;
      $r = civicrm_api3('banking_account','create',array('id' => $baid, 'contact_id'=>$contact_id));
    }
  }

}