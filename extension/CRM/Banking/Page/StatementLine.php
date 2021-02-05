<?php
use CRM_Banking_ExtensionUtil as E;

class CRM_Banking_Page_StatementLine extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Statement Lines'));
    $session = CRM_Core_Session::singleton();
    $this->assign('can_delete', CRM_Core_Permission::check('administer CiviCRM'));
    
    $statusApi = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'civicrm_banking.bank_tx_status', 'options' => array('limit' => 0)));
    $statuses = array();
    $statusCount = array();
    foreach($statusApi['values'] as $status) {
      $statuses[$status['id']] = $status;
      $statusCount[$status['name']] = 0;
    }
    $this->assign('statuses', $statuses);
    
    $selectedStatuses = $session->get('org.project60.banking.statementline.statusfilter');
    if ($selectedStatuses) {
      $selectedStatuses = json_decode($selectedStatuses);
    } else {
      $selectedStatuses = array_keys($statuses);  
    }
    
    if (isset($_REQUEST['reset']) && $_REQUEST['reset'] === '1') {
      $selectedStatuses = array_keys($statuses);
    }
    
    if (isset($_REQUEST['status']) && is_array($_REQUEST['status'])) {
      $selectedStatuses = CRM_Utils_Type::escapeAll($_REQUEST['status'], 'Integer', true);
      $session->set('org.project60.banking.statementline.statusfilter', json_encode($selectedStatuses));
    }
    $selectedStatusesWhereClause = "";
    if (count($selectedStatuses)) {
      $selectedStatusesWhereClause = " AND status.id IN (".implode(", ", $selectedStatuses).")";
    }
    $this->assign('selectedStatuses', $selectedStatuses);

    $statement_id = CRM_Utils_Request::retrieve('s_id', 'Positive', CRM_Core_DAO::$_nullObject, TRUE);
    
    $sql = "SELECT tx.*, DATE(value_date) AS date, status.name as status_name, status.label as status_label 
      FROM civicrm_bank_tx tx LEFT JOIN civicrm_option_value status ON status.id = tx.status_id 
      WHERE tx.tx_batch_id = %1
      {$selectedStatusesWhereClause}
      ORDER BY status.weight, value_date";
    $queryParams[1] = array($statement_id, 'Integer');
    $lines = array();
    $lineDao = CRM_Core_DAO::executeQuery($sql, $queryParams);
    while($lineDao->fetch()) {
      $line = array();
      $line['id'] = $lineDao->id;
      $line['date'] = $lineDao->date;
      $line['amount'] = $lineDao->amount;
      $line['currency'] = $lineDao->currency;
      $line['data_parsed'] = json_decode($lineDao->data_parsed, true);
      $line['suggestions'] = json_decode($lineDao->suggestions, true) ?: [];
      $line['suggestion_count'] = count($line['suggestions']);
      $line['status'] = $lineDao->status_label;
      $line['status_name'] = $lineDao->status_name;
      $lines[$lineDao->id] = $line;
      
      $statusCount[$lineDao->status_name] ++;
    }
    
    $this->assign('lines', $lines);
    $this->assign('statement_id', $statement_id);
    $this->assign('status_count', $statusCount);
    $this->assign('list', implode(",", array_keys($lines)));
    
    parent::run();
  }

}
