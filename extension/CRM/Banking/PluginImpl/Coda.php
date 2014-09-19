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

// Example config file:
// {
//   "amounts":  [ "35.00", "(rand(0,20000)-10000)/100" ],
//   "purposes": [ "membership", "donation", "buy yourself something nice" ]
// }

/**
 * @deprecated ?
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Banking_PluginImpl_Coda extends CRM_Banking_PluginModel_Importer {
    protected $cnt_coda_batches;
    protected $cnt_total_tx;
    
    function __construct($config_name) {
        parent::__construct($config_name);
        $this->_ba_ref_types = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_account_reference_type');  
    }

     /** 
   * the plugin's user readable name
   * 
   * @return string
   */
    static function displayName(){
        return 'CODA Convertor';
    }

    /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
    static function does_import_files(){
        return FALSE;
    }

    /** 
     * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
     * 
     * @return bool
     */
    static function does_import_stream(){
        return TRUE;
    }

    /** 
     * Test if the given file can be imported
     * 
     * @var 
     * @return TODO: data format? 
     */
    function probe_file( $file_path, $params ){
        return FALSE;
    }


    /** 
     * Import the given file
     * 
     * @return TODO: data format? 
     */
    function import_file( $file_path, $params ){
        $this->reportDone(array());
        return FALSE;
    }

    /** 
     * Test if the configured source is available and ready
     * 
     * @var 
     * @return TODO: data format?
     */
    function probe_stream( $params ){
        return TRUE;
    }  
    
    
    function openTransactionBatch($batch_id=0, $coda_batch=null) {
        $this->reportProgress(0,sprintf(ts("Processing CODA statement %s for account %s"),$coda_batch->sequence,$this->bank_reference));

        if ($this->_current_transaction_batch==NULL) {
            $reference = $coda_batch->sequence.' '.$coda_batch->file;
            $this->_current_transaction_batch = new CRM_Banking_BAO_BankTransactionBatch();
            $this->_current_transaction_batch_attributes = array();
            
            $tbbatch = civicrm_api('banking_transaction_batch', 'getSingle', array('reference'=>$reference, 'version'=>3));
            if(!empty($tbbatch)){
                $batch_id = $tbbatch['id'];
            }
            if ($batch_id) {
                // load an existing batch
                $this->_current_transaction_batch->get('id', $batch_id);
                $this->_current_transaction_batch_attributes['isnew'] = FALSE;
                $this->_current_transaction_batch_attributes['sum'] = ($this->_current_transaction_batch->ending_balance - $this->_current_transaction_batch->starting_balance);
            } else {
                $this->_current_transaction_batch->issue_date = date('YmdHis');
                $this->_current_transaction_batch->reference = $reference;
                $this->_current_transaction_batch->sequence = $coda_batch->sequence;
                $this->_current_transaction_batch->starting_balance = $coda_batch->starting_balance;
                //$this->_current_transaction_batch->ending_balance = $coda_batch->ending_balance;
                $this->_current_transaction_batch->currency = $coda_batch->currency;
                $this->_current_transaction_batch->starting_date = date('YmdHis', strtotime($coda_batch->starting_date));
                $this->_current_transaction_batch->ending_date = date('YmdHis', strtotime($coda_batch->ending_date));
                $this->_current_transaction_batch->tx_count = $coda_batch->tx_count;

                $this->_current_transaction_batch->save();
                $this->_current_transaction_batch_attributes['isnew'] = TRUE;
                $this->_current_transaction_batch_attributes['sum'] = 0;
            }
        } else {
          $this->reportProgress($progress, 
                      ts("Internal error: trying to open BTX batch before closing an old one."), 
                      CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
        }
        return $this->_current_transaction_batch->id;
    }  

     /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
    function import_stream( $params ){
        $config = $this->_plugin_config;            
        $breftypeid = $this->_ba_ref_types[$config->bank_account_reference_type]['value'];
        
        $baref = civicrm_api('banking_account_reference', 'get', array('version'=>3, 'reference'=>$config->account, 'reference_type_id'=>$breftypeid));
        $bankacc = civicrm_api('banking_account', 'get', array('version'=>3, 'id'=>$baref['values'][$baref['id']]['ba_id']));
        $this->bank_account_id = $bankacc['id'];
        $this->bank_reference = $baref['values'][$baref['id']]['reference'];
        $state = $this->_plugin_state;
        $this->_compare_btx_fields = array( 'bank_reference'=>TRUE, 'amount'=>TRUE, 'value_date'=>TRUE,  'sequence'=>TRUE, 'version'=>3);  //'booking_date'=>TRUE, 'currency'=>TRUE,, 'version'=>3 

        $coda_batches = $this->get_coda_batch_all($config->bank_account_reference_type, $this->bank_reference);       
        $cnt = 0;
        foreach($coda_batches as $coda_batch) {
            $batch_id = $this->openTransactionBatch(0, $coda_batch);

            if(!isset($state->balance)){
                $state->last_action = 'Coda Import';
                $state->balance = $coda_batch->starting_balance;
                $state->balance_date = $coda_batch->starting_date;
            }
            if($state->balance!=$coda_batch->starting_balance){
                //todo missing codafiles ?
               // CRM_Core_Error::fatal("Coda batch missing : ");
               // return;
            }

            $sql = 'select * from civicrm_coda_tx where coda_batch_id='.$coda_batch->id.' order by sequence';
            $coda_tx = CRM_Core_DAO::executeQuery($sql);

            while($coda_tx->fetch()){
                $cnt += 1;
                //raw filter 
                if(empty($coda_tx->bban) && empty($coda_tx->iban)){  //for testing only ; must be replaced
                    continue;
                }

                $data_raw=array(
                    'name'=>$coda_tx->name,
                    'move_msg'=>$coda_tx->move_message,
                    'info_msg'=>$coda_tx->info_message,                  
                );
                $data_parsed = array(
                    'name'=>$coda_tx->name,
                    'street_address'=>$coda_tx->streetname.' '.$coda_tx->streetnumber,
                    'postal_code'=>$coda_tx->postal_code,
                    'city'=>$coda_tx->city,
                    'bic'=>$coda_tx->bic,
                    'bban'=>$coda_tx->bban,
                    'iban'=>$coda_tx->iban,
                    'txncode'=>$coda_tx->txncode,
                    'customer_ref'=>$coda_tx->customer_ref,
                    'move_struct_code'=>$coda_tx->move_structured_code,
                    'purpose'=>$coda_tx->move_message,                  
                    //...todo
                );
                $party_bank_account_id = $this->getOrCreateBankAccount($coda_tx);
                $btx = array(
                      'version' => 3,
                      'debug' => 1,
                      'amount' => $coda_tx->amount,
                      'bank_reference' => 'Coda '.sprintf("%08s", $batch_id).'-'.$coda_batch->sequence.'-'.$coda_tx->sequence.' '.$coda_batch->file,       
                      'value_date' => date('YmdHis', strtotime($coda_tx->value_date)),   
                      'booking_date' => date('YmdHis', strtotime($coda_tx->booking_date)), 
                      'currency' => 'EUR',                          // EUR
                      'type_id' => 0,                               // TODO: lookup type ?
                      'status_id' => 0,                             // TODO: lookup status new
                      'data_raw' => json_encode($data_raw),                   
                      'data_parsed' => json_encode($data_parsed),   // name, purpose
                      'ba_id' => $this->bank_account_id,                                // TODO: config
                      'party_ba_id' => $party_bank_account_id,                          // TODO: config
                      'tx_batch_id' => $batch_id,                        // TODO: create batch
                      'sequence' => $coda_tx->sequence,                             
                    );
                //echo '<hr>';print_r($btx);
                //todo get progress
                $progress = $cnt/$this->cnt_total_tx;
                $duplicate = $this->checkAndStoreBTX($btx, $progress, $params);

            }
            //$state->balance = $dao->dateNewBalance;
            $batch = $this->closeTransactionBatch(); 
            if($cnt>10){
                //return;   //!!testing
            }
            
            $this->close_coda_batch($coda_batch->id);
        }
        $this->reportDone();

    }

  
  protected function &get_coda_batch_all($ba_reference_type, $ba_reference, $status='new'){
      $coda_batches = array();
      $sql = 'SELECT COUNT(r.id) AS tx_count, f.id, f.`currency`, f.`sequence`, f.`date_created_by_bank`, f.`starting_date`, f.`ending_date`, f.`starting_balance`, f.`ending_balance`, f.`file`
              FROM civicrm_coda_batch f, civicrm_coda_tx r WHERE 
              f.`status`="'.$status.'" AND
              r.`coda_batch_id`=f.id AND 
              f.'.$ba_reference_type.'="'.$ba_reference.'" GROUP BY f.id ORDER BY f.sequence, r.`sequence`';
      $dao = CRM_Core_DAO::executeQuery($sql);
      $cnt_coda_batches = $cnt_total_tx = 0;
      while ($dao->fetch()) {      
          $coda_batches[$dao->id] = json_decode(json_encode($dao));
          $cnt_coda_batches += 1;
          $cnt_total_tx += $dao->tx_count;
      } 
      $this->cnt_coda_batches = $cnt_coda_batches;
      $this->cnt_total_tx = $cnt_total_tx;
      return $coda_batches;
  }

    
  protected function close_coda_batch($id){
      $sql = "UPDATE civicrm_coda_batch SET status = 'PROCESSED' WHERE id = $id";
      $dao = CRM_Core_DAO::executeQuery($sql);
  }
   
  public function getOrCreateBankAccount(&$coda_tx){
    $refs = array();
    if(isset($coda_tx->iban) && !empty($coda_tx->iban)){
        $refs['iban'] = $coda_tx->iban;
    }
    if(isset($coda_tx->bban) && !empty($coda_tx->bban)){
        $refs['bban'] = $coda_tx->bban;
    }
    if(!array_key_exists('iban', $refs) || !array_key_exists('bban', $refs)){
        //todo take the main ba
    }
    foreach($refs as $type=>$ref){
        $bank_account_refs = array();
        $breftypeid = $this->_ba_ref_types[$type]['value'];
        $options = array('reference_type_id'=>$breftypeid, 'reference'=>$ref, 'version'=>3);
        $result = civicrm_api('banking_account_reference', 'get', $options);
        if($result['count']!=0){
            $bank_account_refs[$type] = $result['values'][$result['id']];            
        }
    }
    /*
    if(isset($coda_tx->bic) && !empty($coda_tx->bic)){
        $refs['bic'] = $coda_tx->bic;
    }   
     */
    if(empty($bank_account_refs)){
        $bank_account = new CRM_Banking_BAO_BankAccount();
        $bank_account->description = $coda_tx->name;
        $data_raw = array(
            'name'=>$coda_tx->name,
            'info_msg'=>$coda_tx->info_message,
        );
        $data_parsed = array(
            'name'=>$coda_tx->name,
            'street_address'=> trim($coda_tx->streetname .' '.$coda_tx->streetnumber),
            'postal_code'=>$coda_tx->postal_code,
            'city'=>$coda_tx->city,
            'country_code'=>$coda_tx->country_code,
            'bic'=>$coda_tx->bic,
        );
        $bank_account->created_date = date('YmdHis');
        $bank_account->modified_date = date('YmdHis');
        $bank_account->data_raw = json_encode($data_raw);
        $bank_account->data_parsed = json_encode($data_parsed);
        $bank_account->save();
        
        $ma = new CRM_Banking_Helpers_MatchAddress($bank_account);
        if($ma->findAddress()){
            $ma->updateDataParsed();
        }
    }else{
        $ba_ref = each($bank_account_refs);
        $result = civicrm_api('banking_account', 'get', array('id'=>$ba_ref['id'], 'version'=>3));
        $bank_account = (object) $result['values'][$result['id']];        
    }
    foreach($refs as $type=>$ref){
        if(!array_key_exists($type, $bank_account_refs)){
            $bank_account_ref = new CRM_Banking_BAO_BankAccountReference();
            $bank_account_ref->reference = $coda_tx->$type;
            $bank_account_ref->reference_type_id = $this->_ba_ref_types[$type]['value'];
            $bank_account_ref->ba_id = $bank_account->id;
            $bank_account_ref->save();
        }
    }
    return $bank_account->id;    
  }
  
  
  
}

