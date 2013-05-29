<?php

require_once 'CRM/Core/Page.php';
require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Review extends CRM_Core_Page {
  function run() {
    // Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Review Bank Transaction'));

    $pid = $_REQUEST['id'];

    // check if we are requested to run the matchers again
    if (isset($_REQUEST['run'])) {
        // run the matchers!
        $engine = CRM_Banking_Matcher_Engine::getInstance();
        $btx_bao = new CRM_Banking_BAO_BankTransaction();
        $btx_bao->get('id', $pid);
        $engine->match($btx_bao);
    }

    // load the btx
    $result = civicrm_api('BankingTransaction', 'get', array('version' => 3,'id' => $pid));
    if ($result['is_error']) {
        CRM_Core_Session::setStatus(ts("Couldn't find banking transaction."), ts('Not found!'), 'alert');
        // add dummy values
        $btx = array( 'id' => 0, );
    } else {
        $btx = $result['values'][$pid];
    }

    // parse structured data
    $this->assign('payment', $btx);
    $this->assign('payment_data_parsed', json_decode($btx['data_parsed'], true));

    /*
  	// Sample data
    $payment_rows = array(
        array('date' => 'March 25th, 2013 1:30 PM', 'amount' => '35,00 €', 'account_owner' => 'Endres, Björn', 'source' => '8213749934', 'target' => '2143988492', 'state' => 'processed', 'target_name' => 'Main Account'),
        array('date' => 'March 21th, 2013 2:13 PM', 'amount' => '99,00 €', 'account_owner' => 'Unknown', 'source' => '235345345', 'target' => '2143988492', 'state' => 'needs Review', 'target_name' => 'Main Account'),
        array('date' => 'April 4th, 2013 10:30 AM', 'amount' => '35,00 €', 'account_owner' => 'Siebert, Detlev', 'source' => '34524325345', 'target' => '2143988492', 'state' => 'processed', 'target_name' => 'Main Account'),
        array('date' => 'March 25th, 2013 1:30 PM', 'amount' => '3,00 €', 'account_owner' => 'Schuttenberg, F.', 'source' => '432553245', 'target' => '2143988492', 'state' => 'needs Review', 'target_name' => 'Main Account'),
        array('date' => 'March 21st, 2013 4:30 PM', 'amount' => '1000,00 €', 'account_owner' => 'Unknown', 'source' => '5345234', 'target' => '2143988492', 'state' => 'needs Review', 'target_name' => 'Main Account'),
        array('date' => 'March 20th, 2013 3:10 PM', 'amount' => '20,00 €', 'account_owner' => 'Unknown', 'source' => '123423534', 'target' => '2143988492', 'state' => 'needs Review', 'target_name' => 'Main Account'),
        array('date' => 'March 30th, 2013 11:11 AM', 'amount' => '35,00 €', 'account_owner' => 'Unknown', 'source' => '5435234345', 'target' => '2143988423', 'state' => 'needs Review', 'target_name' => 'Campaign Account "Save the Whales"'),
    );*/
	
	$potential_actions = array(
		array('type' => 'membership fee'),
		array('type' => 'donation'),
		array('type' => 'new donation'),
	);

	shuffle($potential_actions);
	$actions = array_slice($potential_actions, 0, 2);
	$actions[0]['color'] = '#9cca5d';
	$actions[0]['probability'] = rand(70, 100) . '%';
	$actions[1]['color'] = '#c8dbb8';
	$actions[1]['probability'] = rand(40, 70) . '%';


	// exend 
	array_push($actions,
		array('probability' => '', 'type' => 'manual', 'color' => '#aa9e9e'),
		array('probability' => '', 'type' => 'not civicrm', 'color' => '#f4f4ed')
		);

    $this->assign('actions', $actions);
	

    /*

    $pid = $_GET['pid'];
    if ($pid < count($payment_rows)) $this->assign('payment', $payment_rows[$pid]);

    $next_pid = $pid + 1;
    if ($next_pid < count($payment_rows)) $this->assign('next_pid', $next_pid);
    */
    parent::run();
  }
}
