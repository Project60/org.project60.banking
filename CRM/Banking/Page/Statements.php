<?php
use CRM_Banking_ExtensionUtil as E;

require_once 'CRM/Banking/Helpers/URLBuilder.php';

class CRM_Banking_Page_Statements extends CRM_Core_Page {

  protected $_pager;

  public function run() {
    $config = CRM_Core_Config::singleton();
    CRM_Utils_System::setTitle(E::ts('Banking statements'));
    $this->assign('can_delete', CRM_Core_Permission::check('administer CiviCRM'));
    $this->assign('url_export_selected_payments', banking_helper_buildURL('civicrm/banking/export', array('s_list'=>"__selected__")));

    $statements = array();
    // collect an array of target accounts, serving to limit the display
    $target_accounts = array();

    $statementSelect = "SELECT
      btxb.id AS id,
      ba.id AS ba_id,
      reference,
      btxb.sequence AS sequence,
      DATE(starting_date) AS starting_date,
      DATE(ending_date) AS ending_date,
      tx_count,
      ba.data_parsed AS data_parsed,
      SUM(btx.amount) AS total,
      btx.currency AS currency";
    $statementFrom = "FROM civicrm_bank_tx_batch AS btxb
      LEFT JOIN civicrm_bank_tx AS btx ON btx.tx_batch_id = btxb.id
      LEFT JOIN civicrm_bank_account AS ba ON ba.id = btx.ba_id";
    $statementWhere = "WHERE ";
    $statementGroupBy = "GROUP BY btxb.id, ba_id, reference, btxb.sequence, starting_date, ending_date, tx_count, ba.data_parsed, btx.currency ";
    $statementOrderBy = "ORDER BY starting_date DESC";
    $statementLimit = "LIMIT %1, %2";
    $paramCount = 2;
    $queryParams = array();

    $statementsWhereClauses = array();
    $statementsWhereClauses[] = "1";

    $target_ba_id = CRM_Utils_Request::retrieve('target_ba_id', 'Integer', CRM_Core_DAO::$_nullObject, false, -1);
    if ($target_ba_id > 0) {
      $paramCount++;
      $statementsWhereClauses[] = "ba_id = %{$paramCount}";
      $queryParams[$paramCount] = array($target_ba_id, 'Integer');
    } elseif ($target_ba_id === 0) {
      $statementsWhereClauses[] = "ba_id IS null";
    }

    $include_completed = CRM_Utils_Request::retrieve('include_completed', 'Boolean');
    if (!$include_completed) {
      $statementsWhereClauses[] = "btxb.id IN (SELECT tx.tx_batch_id FROM civicrm_bank_tx tx LEFT JOIN civicrm_option_value status ON status.id = tx.status_id WHERE status.name = 'new' OR status.name = 'suggestions')";
    }

    $date = CRM_Utils_Request::retrieve('date', 'String');
    if ($date) {
      $strDate = CRM_Utils_Date::processDate($date);
      $strDate = CRM_Utils_Date::customFormat($strDate, '%Y-%m-%d');
      $paramCount++;
      $statementsWhereClauses[] = "(starting_date >= %{$paramCount}) AND (ending_date <= %{$paramCount})";
      $queryParams[$paramCount] = array($strDate, 'String');
    }

    $statementWhere .= implode(' AND ', $statementsWhereClauses);

    $count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) {$statementFrom} {$statementWhere} {$statementGroupBy}", $queryParams);

    $params['total'] = $count;
    $params['currentPage'] = $this->get(CRM_Utils_Pager::PAGE_ID);
    $params['rowCount'] = CRM_Utils_Pager::ROWCOUNT;
    $params['status'] = E::ts('Statements %%StatusMessage%%');
    $this->_pager = new CRM_Utils_Pager($params);

    $sql = "{$statementSelect} {$statementFrom} {$statementWhere} {$statementGroupBy} {$statementOrderBy} {$statementLimit}";
    list($offset, $limit) = $this->_pager->getOffsetAndRowCount();
    $queryParams[1] = array($offset, 'Integer');
    $queryParams[2] = array($limit, 'Integer');

    $stmt = CRM_Core_DAO::executeQuery($sql, $queryParams);
    while($stmt->fetch()) {
      // look up the target account
      $target_name = "";
      $target_info = json_decode($stmt->data_parsed);
      if (isset($target_info->name)) {
        $target_name = $target_info->name;
      }

      // finally, create the data row
      $row = array(
        'id' => $stmt->id,
        'reference' => $stmt->reference,
        'sequence' => $stmt->sequence,
        'total' => $stmt->total,
        'currency' => $stmt->currency,
        'starting_date' => $stmt->starting_date,
        'ending_date' => $stmt->ending_date,
        'count' => $stmt->tx_count,
        'target' => $target_name,
        'status' => array('new' => 0, 'suggestions' => 0, 'processed' => 0, 'ignored' => 0),
      );
      $statements[$stmt->id] = $row;

      if ($stmt->ba_id) {
        $target_accounts[$stmt->ba_id] = $target_name;
      } else {
        $target_accounts[0] = E::ts('Unknown account');
      }
    }

    // Build the status count for each statement.
    $batchIds = implode(',', array_keys($statements));
    if (count($statements)) {
      $statusCountSql = "SELECT COUNT(*) as count, status.name as status, tx.tx_batch_id as batch_id
                        FROM civicrm_bank_tx tx
                        LEFT JOIN civicrm_option_value status ON status.id = tx.status_id
                        WHERE tx.tx_batch_id IN ({$batchIds})
                        GROUP BY tx.tx_batch_id, tx.status_id";

      $statusDao = CRM_Core_DAO::executeQuery($statusCountSql);
      while ($statusDao->fetch()) {
        if (isset($statements[$statusDao->batch_id])) {
          $statements[$statusDao->batch_id]['status'][$statusDao->status] = $statusDao->count;
        }
      }
    }

    $this->assign('statements', $statements);
    $this->assign_by_ref('pager', $this->_pager);
    $this->assign('target_accounts', $target_accounts);
    $this->assign('target_ba_id', $target_ba_id);
    $this->assign('include_completed', $include_completed);
    $this->assign('date', $date);

    // Set attributes for the datepicker
    $dateParams['name'] = 'searchDate';
    $dateFormat = array();
    CRM_Core_DAO::commonRetrieve('CRM_Core_DAO_PreferencesDate', $dateParams, $dateFormat);
    $dateAttributes['format'] = $dateFormat['date_format'];
    $dateAttributes['startOffset'] = $dateFormat['start'];
    $dateAttributes['endOffset'] = $dateFormat['end'];
    if (empty($dateAttributes['format'])) {
      $dateAttributes['format'] = $config->dateInputFormat;
    }
    $this->assign('date_attributes', $dateAttributes);

    parent::run();
  }

}
