<?php

class CRM_Banking_PluginModel_Action_CreateContribution {

  public function describe($params, $btx) {
    $ftp = new CRM_Financial_BAO_FinancialType();
    $ftp->get($params->financial_type_id);
    return "creating a contribution of type '{$ftp->name}' for <b>{$btx->amount} {$btx->currency}</b>";
  }

  public function execute($params, $btx) {
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