<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
    
require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';
require_once 'CRM/Banking/Helpers/URLBuilder.php';

class CRM_Banking_Page_Payments extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Payments'));

    // look up the payment states
    $payment_states = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');

    if (isset($_REQUEST['show']) && $_REQUEST['show']=="statements") {
        // STATEMENT MODE REQUESTED
        $this->build_statementPage($payment_states);

    } else {
        // PAYMENT MODE REQUESTED
        $this->build_paymentPage($payment_states);
    }

    // URLs
    $this->assign('url_show_payments', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'payments')));
    $this->assign('url_show_statements', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'statements')));

    $this->assign('url_show_payments_new', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['new']['id']), array('show')));
    $this->assign('url_show_payments_analysed', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['suggestions']['id']), array('show')));
    $this->assign('url_show_payments_completed', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['processed']['id'].",".$payment_states['ignored']['id']), array('show')));

    $this->assign('url_review_selected_payments', banking_helper_buildURL('civicrm/banking/review', array('list'=>"__selected__")));
    $this->assign('url_process_selected_payments', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('process'=>"__selected__"))));
    $this->assign('url_export_selected_payments', banking_helper_buildURL('civicrm/banking/export', array('list'=>"__selected__")));
    $this->assign('url_delete_selected_payments', banking_helper_buildURL('civicrm/banking/payments',  $this->_pageParameters(array('delete'=>"__selected__"))));

    parent::run();
  }

  /****************
   * STATMENT MODE
   ****************/
  function build_statementPage($payment_states) {
    // DELETE ITEMS (if any)
    $this->deleteItems('BankingTransactionBatch', ts('statements'));

    // read all batches
    $params = array('version' => 3);
    $result = civicrm_api('BankingTransactionBatch', 'get', $params);
    $statement_rows = array();
    foreach ($result['values'] as $entry) {
        $info = $this->investigate($entry['id'], $payment_states);
        array_push($statement_rows,
            array(  
                    'id' => $entry['id'], 
                    'reference' => $entry['reference'], 
                    'date' => $entry['starting_date'], 
                    'count' => $entry['tx_count'], 
                    'target' => $info['target_account'],
                    'analysed' => $info['analysed'].'%',
                    'completed' => $info['completed'].'%',
                )
        );
    }

    $this->assign('rows', $statement_rows);
    $this->assign('status_message', sizeof($statement_rows).' incomplete statements.');
    $this->assign('show', 'statements');        
  }


  /****************
   * PAYMENT MODE
   ****************/
  function build_paymentPage($payment_states) {
    // DELETE ITEMS (if any)
    $this->deleteItems('BankingTransaction', ts('payments'));
    
    // read all transactions
    $btxs = $this->load_btx($payment_states);
    $payment_rows = array();
    foreach ($btxs as $entry) {
        $status = $payment_states[$entry['status_id']]['label'];
        array_push($payment_rows, 
            array(  
                    'id' => $entry['id'], 
                    'date' => $entry['value_date'], 
                    'amount' => (isset($entry['amount'])?$entry['amount']:"unknown"), 
                    'account_owner' => 'TODO', 
                    'source' => (isset($entry['party_ba_id'])?$entry['party_ba_id']:"unknown"),
                    'target' => (isset($entry['ba_id'])?$entry['ba_id']:"unknown"),
                    'state' => $status,
                    'url_link' => CRM_Utils_System::url('civicrm/banking/review', 'id='.$entry['id']),
                )
        );
    }

    $this->assign('rows', $payment_rows);
    $this->assign('status_message', sizeof($payment_rows).' unprocessed payments.');
    $this->assign('show', 'payments');        
  }


  /****************
   *    HELPERS
   ****************/


  function deleteItems($type, $name) {
    // DELETE ITEMS (if any)
    if (isset($_REQUEST['delete'])) {
        $list = explode(",", $_REQUEST['delete']);
        $params = array('version' => 3);
        $failed = 0;
        // delete all these
        foreach ($list as $pid) {
            $params['id'] = $pid;
            $result = civicrm_api($type, 'delete', $params);
            if (isset($result['is_error']) && $result['is_error']) {
                $failed += 1;
            }
        }
        if ($failed) {
            CRM_Core_Session::setStatus(sprintf(ts('Failed to delete %d of %d selected %s.'), $failed, count($list), $name), ts('Deletion problems'), 'alert');
        } else {
            CRM_Core_Session::setStatus(sprintf(ts('Deleted %d selected %s.'), count($list), $name), ts('Deletion succesfull'), 'info');
        }
    }    
  }

  /**
   * will iterate through all transactions in the given statements and
   * return an array with some further information:
   *   'analysed'      => percentage of analysed statements
   *   'completed'      => percentage of completed statements
   *   'target_account' => the target account
   */
  function investigate($stmt_id, $payment_states) {
    // go over all transactions to find out rates and data
    $target_account = "Unknown";
    $analysed_state_id = $payment_states['suggestions']['id'];
    $analysed_count = 0;
    $completed_state_id = $payment_states['processed']['id'];
    $completed_count = 0;
    $count = 0;


    $btx_query = array('version' => 3, 'tx_batch_id' => $stmt_id);
    $btx_result = civicrm_api('BankingTransaction', 'get', $btx_query);
    foreach ($btx_result['values'] as $btx) {
        $count += 1.0;
        if (isset($btx['ba_id']))
            $target_account = $btx['ba_id'];

        if ($btx['status_id']==$completed_state_id) {
            $completed_count += 1;
        } else if ($btx['status_id']==$analysed_state_id) {
            $analysed_count += 1;
        }
    }
    
    if ($count) {
      return array(
        'analysed'       => ($analysed_count / $count * 100.0),
        'completed'      => ($completed_count / $count * 100.0),
        'target_account' => $target_account
        );
    } else {
      return array(
        'analysed'       => 0,
        'completed'      => 0,
        'target_account' => $target_account
        );
    }
  }


   /**
   * load BTXs according to the 'status_ids' and 'batch_ids' values in $_REQUEST
   *
   * @return array of (later: up to $page_size) BTX objects (as arrays)
   */
  function load_btx($payment_states) {  // TODO: later add: $page_nr=0, $page_size=50) {
    // set defaults
    $status_ids = array($payment_states['new']['id']);
    $batch_ids = array(NULL);

    if (isset($_REQUEST['status_ids']))
        $status_ids = explode(',', $_REQUEST['status_ids']);
    if (isset($_REQUEST['batch_ids']))
        $batch_ids = explode(',', $_REQUEST['batch_ids']);

    // run the queries
    $results = array();
    foreach ($status_ids as $status_id) {
        foreach ($batch_ids as $batch_id) {
            //$results = array_merge($results, $this->_findBTX($status_id, $batch_id));
            $results += $this->_findBTX($status_id, $batch_id);
        }
    }

    return $results;
  }

  function _findBTX($status_id, $batch_id) {
    $params = array('version' => 3);
    if ($status_id!=NULL) $params['status_id'] = $status_id;
    if ($batch_id!=NULL) $params['tx_batch_id'] = $batch_id;
    $result = civicrm_api('BankingTransaction', 'get', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::error(sprintf(ts("Error while querying BTX with parameters '%s'!"), implode(',', $params)));
      return array();
    } else {
      return $result['values'];
    }
  }

  /**
   * creates an array of all properties defining the current page's state
   * 
   * if $override is given, it will be taken into the array regardless
   */
  function _pageParameters($override=array()) {
    $params = array();
    if (isset($_REQUEST['status_ids']))
        $params['status_ids'] = $_REQUEST['status_ids'];
    if (isset($_REQUEST['tx_batch_id']))
        $params['tx_batch_id'] = $_REQUEST['tx_batch_id'];
    if (isset($_REQUEST['show']))
        $params['show'] = $_REQUEST['show'];

    foreach ($override as $key => $value) {
        $params[$key] = $value;
    }
    return $params;
  }
}
