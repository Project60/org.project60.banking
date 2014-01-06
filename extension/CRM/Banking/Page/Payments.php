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

    if (!isset($_REQUEST['status_ids'])) {
      $_REQUEST['status_ids'] = $payment_states['new']['id'];
    }

    if (isset($_REQUEST['show']) && $_REQUEST['show']=="payments") {
        // PAYMENT MODE REQUESTED
        $this->build_paymentPage($payment_states);
        $list_type = 'list';
    } else {
        // STATEMENT MODE REQUESTED
        $this->build_statementPage($payment_states);
        $list_type = 's_list';
    }

    // URLs
    global $base_url;
    $this->assign('base_url', $base_url);

    $this->assign('url_show_payments', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'payments')));
    $this->assign('url_show_statements', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'statements')));

    $this->assign('url_show_payments_new', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['new']['id']), array('show')));
    $this->assign('url_show_payments_analysed', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['suggestions']['id']), array('show')));
    $this->assign('url_show_payments_completed', banking_helper_buildURL('civicrm/banking/payments', array('status_ids'=>$payment_states['processed']['id'].",".$payment_states['ignored']['id']), array('show')));

    $this->assign('url_review_selected_payments', banking_helper_buildURL('civicrm/banking/review', array($list_type=>"__selected__")));
    $this->assign('url_process_selected_payments', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('process'=>"__selected__"))));
    $this->assign('url_export_selected_payments', banking_helper_buildURL('civicrm/banking/export', array($list_type=>"__selected__")));
    $this->assign('url_delete_selected_payments', banking_helper_buildURL('civicrm/banking/payments',  $this->_pageParameters(array('delete'=>"__selected__"))));

    // status filter button styles
    if (isset($_REQUEST['status_ids']) && strlen($_REQUEST['status_ids'])>0) {
      if ($_REQUEST['status_ids']==$payment_states['new']['id']) {
        $this->assign('button_style_new', "color:green");
      } else if ($_REQUEST['status_ids']==$payment_states['suggestions']['id']) {
        $this->assign('button_style_analysed', "color:green");
      } else if ($_REQUEST['status_ids']==$payment_states['processed']['id'].",".$payment_states['ignored']['id']) {
        $this->assign('button_style_completed', "color:green");
      } else {
        $this->assign('button_style_custom', "color:green");
      }
    }

    parent::run();
  }

  /****************
   * STATMENT MODE
   ****************/
  function build_statementPage($payment_states) {
    // DELETE ITEMS (if any)
    if (isset($_REQUEST['delete'])) {
      $payment_list = CRM_Banking_Page_Payments::getPaymentsForStatements($_REQUEST['delete']);
      $this->deleteItems($payment_list, 'BankingTransaction', ts('payments'));
      $this->deleteItems($_REQUEST['delete'], 'BankingTransactionBatch', ts('statements'));
    }

    // RUN ITEMS (if any)
    if (isset($_REQUEST['process'])) {
      $payment_list = CRM_Banking_Page_Payments::getPaymentsForStatements($_REQUEST['process']);
      $this->processItems($payment_list);
    }

    $statements_new = array();
    $statements_analysed = array();
    $statements_completed = array();
    
    // TODO: WE NEED a tx_batch status field, see https://github.com/Project60/CiviBanking/issues/20
    $sql_query =    // this query joins the bank_account table to determine the target account
      "SELECT civicrm_bank_tx_batch.id AS id, reference, starting_date, tx_count, ba_id, civicrm_bank_account.data_parsed as data_parsed
         FROM civicrm_bank_tx_batch 
         LEFT JOIN civicrm_bank_tx ON civicrm_bank_tx.tx_batch_id = civicrm_bank_tx_batch.id 
         LEFT JOIN civicrm_bank_account ON civicrm_bank_account.id = civicrm_bank_tx.ba_id 
         GROUP BY id
         ORDER BY starting_date ASC;";
    $stmt = CRM_Core_DAO::executeQuery($sql_query);
    while($stmt->fetch()) {
      // check the states
      $info = $this->investigate($stmt->id, $payment_states);

      // look up the target account
      $target_name = ts("Unknown");
      $target_info = json_decode($stmt->data_parsed);
      if (isset($target_info->name)) {
        $target_name = $target_info->name;
      }

      // finally, create the data row
      $row = array(  
                    'id' => $stmt->id, 
                    'reference' => $stmt->reference, 
                    'date' => strtotime($stmt->starting_date), 
                    'count' => $stmt->tx_count, 
                    'target' => $target_name,
                    'analysed' => $info['analysed'].'%',
                    'completed' => $info['completed'].'%',
                );

      // sort it
      if ($info['completed']==100) {
        array_push($statements_completed, $row);
      } else {
        if ($info['analysed']>0) {
          array_push($statements_analysed, $row);
        }
        if ($info['analysed']<100) {
          array_push($statements_new, $row);
        }
      }
    }

    if ($_REQUEST['status_ids']==$payment_states['new']['id']) {
      // 'NEW' mode will show all that have not been completely analysed
      $this->assign('rows', $statements_new);
      $this->assign('status_message', sizeof($statements_new).' incomplete statements.');

    } elseif ($_REQUEST['status_ids']==$payment_states['suggestions']['id']) {
      // 'ANALYSED' mode will show all that have been partially analysed, but not all completed
      $this->assign('rows', $statements_analysed);
      $this->assign('status_message', sizeof($statements_analysed).' analysed statements.');

    } else {
      // 'COMPLETE' mode will show all that have been entirely processed
      $this->assign('rows', $statements_completed);
      $this->assign('status_message', sizeof($statements_completed).' completed statements.');
    }
    $this->assign('show', 'statements');        
  }


  /****************
   * PAYMENT MODE
   ****************/
  function build_paymentPage($payment_states) {
    // DELETE ITEMS (if any)
    if (isset($_REQUEST['delete'])) {
      $this->deleteItems($_REQUEST['delete'], 'BankingTransaction', ts('payments'));
    }

    // RUN ITEMS (if any)
    if (isset($_REQUEST['process'])) {
      $this->processItems($_REQUEST['process']);
    }

    // read all transactions
    $btxs = $this->load_btx($payment_states);
    $payment_rows = array();
    foreach ($btxs as $entry) {
        $status = $payment_states[$entry['status_id']]['label'];
        $data_parsed = json_decode($entry['data_parsed'], true);

        $ba_id = $entry['ba_id'];
        $params = array('version' => 3, 'id' => $ba_id);
        $result = civicrm_api('BankingAccount', 'getsingle', $params);
        
        $pba_id = $entry['party_ba_id'];
        $params = array('version' => 3, 'id' => $pba_id);
        $attached_ba = civicrm_api('BankingAccount', 'getsingle', $params);
        
        $cid = $attached_ba['contact_id'];
        $contact = null;
        if ($cid) {
          $params = array('version' => 3, 'id' => $cid);
          $contact = civicrm_api('Contact', 'getsingle', $params);
        }

        if (isset($attached_ba['description'])) {
          $party = $attached_ba['description'];
        } else {
          if (isset($data_parsed['name'])) {
            $party = "<i>".$data_parsed['name']."</i>";
          } else {
            $party = "<i>".ts("not yet identified.")."</i>";
          }
        }
        
        array_push($payment_rows, 
            array(  
                    'id' => $entry['id'], 
                    'date' => $entry['value_date'], 
                    'sequence' => $entry['sequence'], 
                    'currency' => $entry['currency'], 
                    'amount' => (isset($entry['amount'])?$entry['amount']:"unknown"), 
                    'account_owner' => $result['description'], 
                    'party' => $party,
                    'party_contact' => $contact,
                    'state' => $status,
                    'url_link' => CRM_Utils_System::url('civicrm/banking/review', 'id='.$entry['id']),
                    'payment_data_parsed' => $data_parsed,
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

  function deleteItems($item_list, $type, $name) {
    $list = explode(",", $item_list);
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

  function processItems($item_list) {
    $list = explode(",", $item_list);

    // run the matchers!
    $engine = CRM_Banking_Matcher_Engine::getInstance();
    foreach ($list as $pid) {
      $btx_bao = new CRM_Banking_BAO_BankTransaction();
      $btx_bao->get('id', $pid);        
      $engine->match($btx_bao);
    }
    CRM_Core_Session::setStatus(sprintf(ts('Analysed %d payments.'), count($list)), ts('Analysis completed.'), 'info');
  }

  /**
   * will take a comma separated list of statement IDs and create a list of the related payment ids in the same format
   */
  public static function getPaymentsForStatements($raw_statement_list) {
    $payments = array();
    $raw_statements = explode(",", $raw_statement_list);
    if (count($raw_statements)==0) {
      return '';
    }

    $statements = array();
    # make sure, that the statments are all integers (SQL injection)
    foreach ($raw_statements as $stmt_id) {
      array_push($statements, intval($stmt_id));
    }
    $statement_list = implode(",", $statements);

    $sql_query = "SELECT id FROM civicrm_bank_tx WHERE tx_batch_id IN ($statement_list);";
    $stmt_ids = CRM_Core_DAO::executeQuery($sql_query);
    while($stmt_ids->fetch()) {
      array_push($payments, $stmt_ids->id);
    }
    return implode(",", $payments);
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
    $stmt_id = intval($stmt_id);
    $count = 0;

    $sql_query = "SELECT status_id, COUNT(status_id) AS count FROM civicrm_bank_tx WHERE tx_batch_id=$stmt_id GROUP BY status_id;";
    $stats = CRM_Core_DAO::executeQuery($sql_query);
    // this creates a table: | status_id | count |
    
    $status2count = array();
    while ($stats->fetch()) {
      $status2count[$stats->status_id] = $stats->count;
      $count += $stats->count;
    }

    if ($count) {
      // count the individual values
      $analysed_state_id = $payment_states['suggestions']['id'];
      $analysed_count = 0;
      if (isset($status2count[$analysed_state_id])) {
        $analysed_count = $status2count[$analysed_state_id];
      }
      $completed_state_id = $payment_states['processed']['id'];
      $completed_count = 0;
      if (isset($status2count[$completed_state_id])) {
        $completed_count = $status2count[$completed_state_id];
      }
      $ignored_state_id = $payment_states['ignored']['id'];
      if (isset($status2count[$ignored_state_id])) {
        $completed_count += $status2count[$ignored_state_id];
      }

      return array(
        'analysed'       => round(($analysed_count+$completed_count) / $count * 100.0),
        'completed'      => round($completed_count / $count * 100.0),
        'target_account' => "Unknown"
        );
    } else {
      return array(
        'analysed'       => 0,
        'completed'      => 0,
        'target_account' => "Unknown"
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
    $params = array('version' => 3,'option.limit'=>999);
    if ($status_id!=NULL) $params['status_id'] = $status_id;
    if ($batch_id!=NULL) $params['tx_batch_id'] = $batch_id;
    $result = civicrm_api('BankingTransaction', 'get', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::error(sprintf(ts("Error while querying BTX with parameters '%s'!"), implode(',', $params)));
      return array();
    } elseif (count($result['values'])>=999) {
      CRM_Core_Session::setStatus(sprintf(ts('Internal limit of 1000 transactions hit. Please use smaller statments.'), $failed, count($list), $name), ts('Internal restriction'), 'alert');
      return $result['values'];
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
