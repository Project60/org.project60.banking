<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2023 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Banking_ExtensionUtil as E;

require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';
require_once 'CRM/Banking/Helpers/URLBuilder.php';

class CRM_Banking_Page_Payments extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Bank Transactions'));

    // look up the payment states
    $payment_states = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');

    if (!isset($_REQUEST['status_ids'])) {
      $_REQUEST['status_ids'] = $payment_states['new']['id'];
    }

    if (isset($_REQUEST['show']) && $_REQUEST['show']=="payments") {
        // PAYMENT MODE REQUESTED
        $this->build_paymentPage($payment_states);
        $list_type = 'list';
        CRM_Utils_System::setTitle(E::ts('Bank Transactions'));
    } else {
        // STATEMENT MODE REQUESTED
        $this->build_statementPage($payment_states);
        $list_type = 's_list';
        CRM_Utils_System::setTitle(E::ts('Bank Statements'));
    }

    // URLs
    global $base_url;
    $this->assign('base_url', $base_url);

    $this->assign('url_show_payments', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'payments', $list_type=>"__selected__"), array('status_ids', 'recent')));
    $this->assign('url_show_statements', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'statements'), array('status_ids', 'recent')));

    $this->assign('url_show_payments_new', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('status_ids'=>$payment_states['new']['id']))));
    $this->assign('url_show_payments_analysed', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('status_ids'=>$payment_states['suggestions']['id']))));
    $this->assign('url_show_payments_completed', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('status_ids'=>$payment_states['processed']['id'].",".$payment_states['ignored']['id']))));

    $this->assign('url_review_selected_payments', banking_helper_buildURL('civicrm/banking/review', array($list_type=>"__selected__")));
    $this->assign('url_export_selected_payments', banking_helper_buildURL('civicrm/banking/export', array($list_type=>"__selected__")));

    $this->assign('can_delete', CRM_Core_Permission::check('administer CiviCRM'));

    // status filter button styles
    if (isset($_REQUEST['status_ids']) && strlen($_REQUEST['status_ids'])>0) {
      if ($_REQUEST['status_ids']==$payment_states['new']['id']) {
        $this->assign('button_style_new', "color:lightgreen");
      } else if ($_REQUEST['status_ids']==$payment_states['suggestions']['id']) {
        $this->assign('button_style_analysed', "color:lightgreen");
      } else if ($_REQUEST['status_ids']==$payment_states['processed']['id'].",".$payment_states['ignored']['id']) {
        if (empty($_REQUEST['recent'])) {
          $this->assign('button_style_completed', "color:lightgreen");
        } else {
          $this->assign('button_style_recently_completed', "color:lightgreen");
        }
      } else {
        $this->assign('button_style_custom', "color:lightgreen");
      }
    }

    parent::run();
  }

  /****************
   * STATEMENT MODE
   ****************/
  function build_statementPage($payment_states) {
    $where_clause = ' TRUE ';
    $target_ba_id = null;
    if (isset($_REQUEST['target_ba_id'])) {
      $target_ba_id = $_REQUEST['target_ba_id'];
    }

    // evaluate statement
    $recently_closed_cutoff = CRM_Banking_Config::getRecentlyCompletedStatementCutoff();
    if ($recently_closed_cutoff) {
      $this->assign('url_show_payments_recently_completed', banking_helper_buildURL('civicrm/banking/payments', $this->_pageParameters(array('recent' => 1, 'status_ids'=>$payment_states['processed']['id'].",".$payment_states['ignored']['id']))));
    }

    // FIRST: CALCULATE COUNTS
    // calculate statement counts: NEW (at least one transaction in status new)
    $new_statement_id_list = CRM_Core_DAO::singleValueQuery("
        SELECT GROUP_CONCAT(DISTINCT(tx_batch_id))
        FROM civicrm_bank_tx
        WHERE status_id IN ({$payment_states['new']['id']})
          AND id NOT IN (
            SELECT tx_batch_id
            FROM civicrm_bank_tx
            WHERE status_id IN ({$payment_states['new']['id']})
          );");
    if (empty($new_statement_id_list)) {
      $new_statement_ids = [];
      $new_statement_id_list = ''; // i.e. no such ID
      $this->assign('count_new', 0);
    } else {
      $new_statement_ids = explode(',', $new_statement_id_list);
      $this->assign('count_new', count($new_statement_ids));
    }

    // calculate statement counts: OPEN (at least one transaction in status suggestions)
    $open_statement_id_list = CRM_Core_DAO::singleValueQuery("
        SELECT GROUP_CONCAT(DISTINCT(tx_batch_id))
        FROM civicrm_bank_tx
        WHERE status_id IN ({$payment_states['suggestions']['id']})");
    if (empty($open_statement_id_list)) {
      $open_statement_ids = [];
      $open_statement_id_list = '';
      $open_statement_count = 0;
    } else {
      $open_statement_ids = explode(',', $open_statement_id_list);
      $open_statement_count = count($open_statement_ids);
    }
    $this->assign('count_analysed', $open_statement_count);

    // calculate the number of statements that are not closed
    $non_closed_statement_ids = array_unique(array_merge($open_statement_ids, $new_statement_ids));

    // closed count is merely the total count without the former two
    $total_statement_count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_bank_tx_batch;");
    $closed_statement_count = $total_statement_count - count($non_closed_statement_ids);
    $this->assign('count_completed', $closed_statement_count);

    // add restricted completed list (if enabled)
    if (!empty($_REQUEST['recent']) && $recently_closed_cutoff) {
      $where_clause .= " AND (btxb.starting_date >= DATE(NOW() - {$recently_closed_cutoff})) ";
    }

    // add the 'recently closed' count
    if ($recently_closed_cutoff) {
      if ($non_closed_statement_ids) {
        $non_closed_statement_id_list = implode(',', $non_closed_statement_ids);
      } else {
        $non_closed_statement_id_list = "-1";
      }
      $recently_closed_statement_count = CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(DISTINCT(id))
        FROM civicrm_bank_tx_batch btxb
        WHERE starting_date >= (NOW() - {$recently_closed_cutoff})
        AND btxb.id NOT IN ({$non_closed_statement_id_list});");
      $this->assign('count_recently_completed', $recently_closed_statement_count);
    }

    // collect an array of target accounts, serving to limit the display
    $target_accounts = [];

    // PROCESS REQUESTED CASE
    if ($_REQUEST['status_ids']==$payment_states['new']['id']) {
      // 'NEW' mode will show all that have not been completely analysed
      if ($new_statement_id_list) {
        $where_clause .= "AND btxb.id IN ({$new_statement_id_list})";
        $this->assign('status_message', E::ts("%1 new statements.", [1 => count($new_statement_ids)]));
      } else {
        $where_clause .= "AND FALSE";
        $this->assign('status_message', E::ts("No new statements."));
      }

    } elseif ($_REQUEST['status_ids']==$payment_states['suggestions']['id']) {
      // 'ANALYSED' mode will show all that have been partially analysed, but not all completed
      if ($open_statement_id_list) {
        $where_clause = "btxb.id IN ({$open_statement_id_list})";
        $this->assign('status_message', E::ts("%1 analysed statements.", [1 => $open_statement_count]));
      } else {
        $where_clause = "FALSE";
        $this->assign('status_message', E::ts("No analysed statements."));
      }

    } else {
      // 'COMPLETE' mode will show all that have been entirely processed
      if ($new_statement_id_list) {
        $where_clause .= " AND btxb.id NOT IN ({$new_statement_id_list}) ";
      }
      if ($open_statement_id_list) {
        $where_clause .= " AND btxb.id NOT IN ({$open_statement_id_list}) ";
      }
      if (!empty($_REQUEST['recent']) && $recently_closed_cutoff) {
        $where_clause .= " AND (btxb.starting_date >= DATE(NOW() - {$recently_closed_cutoff})) ";
      }

      $this->assign('status_message', E::ts("%1 closed statements.", [
        1 => $closed_statement_count]));
    }

    // RUN THE STATEMENT QUERY
    $sql_query =
        "SELECT
          btxb.id         AS id,
          ba.id           AS ba_id,
          reference       AS reference,
          btxb.sequence   AS sequence,
          starting_date   AS starting_date,
          tx_count        AS tx_count,
          ba.data_parsed  AS data_parsed,
          sum(btx.amount) AS total,
          btx.currency    AS currency
        FROM civicrm_bank_tx_batch btxb
        LEFT JOIN civicrm_bank_tx btx
               ON btx.tx_batch_id = btxb.id
        LEFT JOIN civicrm_bank_account ba
               ON ba.id = btx.ba_id
        WHERE {$where_clause}"
          .
            ($target_ba_id ? ' AND ba_id = ' . $target_ba_id : '')
          .
          "
        GROUP BY
          id, ba_id, currency
        ORDER BY
          starting_date DESC;";
    $stmt = CRM_Core_DAO::executeQuery($sql_query);

    // process/sort results
    $rows = [];
    while($stmt->fetch()) {
      // check the states
      $info = $this->investigate($stmt->id, $payment_states);

      // look up the target account
      $target_name = E::ts("Unknown");
      $target_info = json_decode($stmt->data_parsed);
      if (isset($target_info->name)) {
        $target_name = $target_info->name;
      }

      // finally, create the data row
      $rows[] = [
          'id' => $stmt->id,
          'reference' => $stmt->reference,
          'sequence' => $stmt->sequence,
          'total' => $stmt->total,
          'currency' => $stmt->currency,
          'date' => strtotime($stmt->starting_date),
          'count' => $stmt->tx_count,
          'target' => $target_name,
          'analysed' => $info['analysed'].'%',
          'completed' => $info['completed'].'%',
      ];

      // collect the target BA
      $target_accounts[ $stmt->ba_id ] = $target_name;
    }

    // evaluate results
    $this->assign('rows', $rows);
    $this->assign('target_accounts', $target_accounts);
    $this->assign('target_ba_id', $target_ba_id);
    $this->assign('show', 'statements');
  }


  /****************
   * PAYMENT MODE
   ****************/
  function build_paymentPage($payment_states) {
    // read all transactions
    $btxs = $this->load_btx($payment_states);
    $payment_rows = array();
    foreach ($btxs as $entry) {
        $status = $payment_states[$entry['status_id']]['label'];
        $data_parsed = json_decode($entry['data_parsed'], true);


        // load the bank accounts and associated contact...
        if (empty($entry['ba_id'])) {
          $bank_account = array('description' => E::ts('Unknown'));
        } else {
          $ba_id = $entry['ba_id'];
          $params = array('version' => 3, 'id' => $ba_id);
          $bank_account = civicrm_api('BankingAccount', 'getsingle', $params);
        }

        $contact = null;
        $attached_ba = null;
        $party = null;
        if (!empty($entry['party_ba_id'])) {
          $pba_id = $entry['party_ba_id'];
          $params = array('version' => 3, 'id' => $pba_id);
          $attached_ba = civicrm_api('BankingAccount', 'getsingle', $params);
        }

        $cid = isset($attached_ba['contact_id']) ? $attached_ba['contact_id'] : null;
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
            $party = "<i>".E::ts("not yet identified.")."</i>";
          }
        }

        // get the highest probability rating for the suggestions
        $probability = 0.0;
        if ('suggestions' == $payment_states[$entry['status_id']]['name']) {
          $suggestions = json_decode($entry['suggestions'], true);

          if (is_array($suggestions)) {
            foreach ($suggestions as $suggestion) {
              if (   !empty($suggestion['probability'])
                  && $probability < (float) $suggestion['probability']) {
                    $probability = (float) $suggestion['probability'];
              }
            }
          }
          $status = sprintf("%s (%d%%)", $status, $probability * 100.0);
        }

      $payment_rows[] = [
          'id'            => $entry['id'],
          'date'          => $entry['value_date'],
          'sequence'      => $entry['sequence'],
          'currency'      => $entry['currency'],
          'amount'        => (isset($entry['amount'])?$entry['amount']:"unknown"),
          'account_owner' => CRM_Utils_Array::value('description', $bank_account),
          'party'         => $party,
          'party_contact' => $contact,
          'state'         => $status,
          'url_link'      => CRM_Utils_System::url('civicrm/banking/review', 'id='.$entry['id']),
          'payment_data_parsed' => $data_parsed,
      ];
    }

    $this->assign('rows', $payment_rows);
    $this->assign('show', 'payments');
    if ($_REQUEST['status_ids']==$payment_states['new']['id']) {
      // 'NEW' mode will show all that have not been completely analysed
      $this->assign('status_message', sprintf(E::ts("%d new transactions."), count($payment_rows)));

    } elseif ($_REQUEST['status_ids']==$payment_states['suggestions']['id']) {
      // 'ANALYSED' mode will show all that have been partially analysed, but not all completed
      $this->assign('status_message', sprintf(E::ts("%d analysed transactions."), count($payment_rows)));

    } else {
      // 'COMPLETE' mode will show all that have been entirely processed
      $this->assign('status_message', sprintf(E::ts("%d completed transactions."), count($payment_rows)));
    }

    // finally, create count statistics
    $this->assignTransactionCountStats($payment_states);
  }


  /****************
   *    HELPERS
   ****************/

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
        'analysed'       => floor(($analysed_count+$completed_count) / $count * 100.0),
        'completed'      => floor($completed_count / $count * 100.0),
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
   * this will try to determine the transaction counts per state
   * for the statement IDs given in the request (s_list)
   */
  function assignTransactionCountStats($payment_states) {
    // pre-assign zero values
    $count_new = 0;
    $count_analysed = 0;
    $count_completed = 0;

    // find batches
    $clean_batch_ids = [];
    if (!empty($_REQUEST['s_list'])) {
      $batch_ids = explode(',', $_REQUEST['s_list']);
      foreach ($batch_ids as $batch_id) {
        if ((int) $batch_id) {
          $clean_batch_ids[] = (int) $batch_id;
        }
      }
    }

    // execute SQL
    if (count($clean_batch_ids)) {
      $batch_id_list = implode(',', $clean_batch_ids);
      $sql = "SELECT status_id, COUNT(id) AS count FROM civicrm_bank_tx WHERE tx_batch_id IN ($batch_id_list) GROUP BY status_id;";
    } else {
      $sql = "SELECT status_id, COUNT(id) AS count FROM civicrm_bank_tx GROUP BY status_id;";
    }
    $query = CRM_Core_DAO::executeQuery($sql);
    while ($query->fetch()) {
      if ($query->status_id == $payment_states['new']['id']) {
        $count_new += $query->count;
      } elseif ($query->status_id == $payment_states['processed']['id']) {
        $count_completed += $query->count;
      } elseif ($query->status_id == $payment_states['ignored']['id']) {
        $count_completed += $query->count;
      } elseif ($query->status_id == $payment_states['suggestions']['id']) {
        $count_analysed += $query->count;
      }
    }

    // pass values to template
    $this->assign('count_new',       $count_new);
    $this->assign('count_analysed',  $count_analysed);
    $this->assign('count_completed', $count_completed);
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
    if (isset($_REQUEST['s_list']))
        $batch_ids = explode(',', $_REQUEST['s_list']);

    // run the queries
    $results = array();
    foreach ($status_ids as $status_id) {
      foreach ($batch_ids as $batch_id) {
        $results = array_merge($results, $this->_findBTX($status_id, $batch_id));
      }
    }

    return $results;
  }

  function _findBTX($status_id, $batch_id) {
    $transaction_display_cutoff = CRM_Banking_Config::transactionViewCutOff();

    $btxs = [];
    $btx_search = new CRM_Banking_BAO_BankTransaction();
    $btx_search->limit($transaction_display_cutoff);
    if (!empty($status_id)) $btx_search->status_id   = (int) $status_id;
    if (!empty($batch_id))  $btx_search->tx_batch_id = (int) $batch_id;
    $btx_search->find();
    while ($btx_search->fetch()) {
      $btxs[] = array(
        'id'          => $btx_search->id,
        'value_date'  => $btx_search->value_date,
        'sequence'    => $btx_search->sequence,
        'currency'    => $btx_search->currency,
        'amount'      => $btx_search->amount,
        'status_id'   => $btx_search->status_id,
        'data_parsed' => $btx_search->data_parsed,
        'suggestions' => $btx_search->suggestions,
        'ba_id'       => $btx_search->ba_id,
        'party_ba_id' => $btx_search->party_ba_id,
        'tx_batch_id' => $btx_search->tx_batch_id,
        );
    }

    if (count($btxs) >= $transaction_display_cutoff) {
      CRM_Core_Session::setStatus(
          E::ts("Internal limit (%1) of transactions to show was exceeded. Please use smaller statements, or adjust the cut-off value in the settings (<a href=\"%2#transaction_list_cutoff\">here</a>).",
          [
            1 => $transaction_display_cutoff,
            2 => CRM_Utils_System::url('civicrm/admin/setting/banking', "reset=1"),
          ]
        ),
        E::ts('Incomplete Transaction List'),
        'alert');
    }

    return $btxs;
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
    if (isset($_REQUEST['s_list']))
        $params['s_list'] = $_REQUEST['s_list'];
    if (isset($_REQUEST['show']))
        $params['show'] = $_REQUEST['show'];

    foreach ($override as $key => $value) {
        $params[$key] = $value;
    }
    return $params;
  }
}
