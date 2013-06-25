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

class CRM_Banking_Page_Payments extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Payments'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));



    if (isset($_REQUEST['show']) && $_REQUEST['show']=="statements") {
        // read all batches
        $params = array('version' => 3);
        $result = civicrm_api('BankingTransactionBatch', 'get', $params);
        $statement_rows = array();
        foreach ($result['values'] as $entry) {
            array_push($statement_rows, 
                array(  
                        'id' => $entry['reference'], 
                        'date' => $entry['starting_date'], 
                        'count' => $entry['tx_count'], 
                        'target' => 'Unknown',
                        'processed' => '0%', 
                        'completed' => '0%', 
                    )
            );
        }

        $this->assign('rows', $statement_rows);
        $this->assign('status_message', sizeof($statement_rows).' incomplete statements.');
        $this->assign('show', 'statements');        


    } else {
        // read all transactions
        $params = array('version' => 3);
        $result = civicrm_api('BankingTransaction', 'get', $params);
        $payment_rows = array();
        foreach ($result['values'] as $entry) {
            array_push($payment_rows, 
                array(  
                        'id' => $entry['id'], 
                        'date' => $entry['value_date'], 
                        'amount' => (isset($entry['amount'])?$entry['amount']:"unknown"), 
                        'account_owner' => 'TODO', 
                        'source' => (isset($entry['party_ba_id'])?$entry['party_ba_id']:"unknown"),
                        'target' => (isset($entry['ba_id'])?$entry['ba_id']:"unknown"),
                        'state' => (isset($entry['status_id'])?$entry['status_id']:"unknown"),
                        'url_link' => CRM_Utils_System::url('civicrm/banking/review', 'id='.$entry['id']),
                    )
            );
        }

        $this->assign('rows', $payment_rows);
        $this->assign('status_message', sizeof($payment_rows).' unprocessed payments.');
        $this->assign('show', 'payments');        
    }

    // URLs
    $this->assign('url_show_payments', CRM_Utils_System::url('civicrm/banking/payments', 'show=payments'));
    $this->assign('url_show_statements', CRM_Utils_System::url('civicrm/banking/payments', 'show=statements'));
    $this->assign('url_show_all', CRM_Utils_System::url('civicrm/banking/review', sprintf('id=%d&list=%s', $entry['id'], 'all')));

    parent::run();
  }
}
