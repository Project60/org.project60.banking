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
class CRM_Banking_PluginModel_Action_CreateContribution {

  public function describe($params, $btx) {
    $ftp = new CRM_Financial_BAO_FinancialType();
    $ftp->get($params->financial_type_id);
    return "creating a contribution of type '{$ftp->name}' for <b>{$btx->amount} {$btx->currency}</b>";
  }

  public function execute($params, $btx, $match) {
    $ba_id = $btx->party_ba_id;
    $ba = civicrm_api('BankingAccount', 'getsingle', array('version' => 3, 'id' => $ba_id));
    $conid = $ba['contact_id'];

    $btx_data_parsed = json_decode($btx->data_parsed, true);
    $options = array(
        'version' => 3,
        'contact_id' => $conid,
        'financial_type_id' => $params->financial_type_id,
        'total_amount' => $btx->amount,
        'currency' => $btx->currency,
        'display_name' => $btx_data_parsed['name'],
        'receive_date' => date('Y-m-d',strtotime($btx->booking_date)),
        'trxn_id' => $btx->bank_reference,
        'source' => 'CODA ',
        'contribution_status_id' => 1,
        );
    $contrib = civicrm_api('Contribution', 'create', $options);
  }

}