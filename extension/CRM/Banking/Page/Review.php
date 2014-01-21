<?php

require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';
require_once 'CRM/Banking/Helpers/URLBuilder.php';

class CRM_Banking_Page_Review extends CRM_Core_Page {

  function run() {
      // Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
      CRM_Utils_System::setTitle(ts('Review Bank Transaction'));

      // Get the current ID
      if (isset($_REQUEST['list'])) {
        $list = explode(",", $_REQUEST['list']);
      } else if (isset($_REQUEST['s_list'])) {
        $list = CRM_Banking_Page_Payments::getPaymentsForStatements($_REQUEST['s_list']);
        $list = explode(",", $list);
      } else {
        $list = array();
        array_push($list, $_REQUEST['id']);
      }

      if (isset($_REQUEST['id'])) {
        $pid = $_REQUEST['id'];
      } else {
        $pid = $list[0];
      }

      // find position in the list
      $index = array_search($pid, $list);
      if ($index>=0) {
        if (isset($list[($index + 1)])) {
          $next_pid = $list[($index + 1)];
        }
        if (isset($list[($index - 1)])) {
          $prev_pid = $list[($index - 1)];
        }
      }

      $btx_bao = new CRM_Banking_BAO_BankTransaction();
      $btx_bao->get('id', $pid);        

      // read the list of BTX statuses
      $choices = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');

      // If the exercution was triggered, run that first
      if (isset($_REQUEST['execute'])) {
        $execute_bao = ($_REQUEST['execute']==$pid) ? $btx_bao : NULL;
        $this->execute_suggestion($_REQUEST['execute_suggestion'], $_REQUEST, $execute_bao, $choices);

        if (!isset($next_pid)) {
          // after execution -> exit if this was the last in the list
          $forward_url = banking_helper_buildURL('civicrm/banking/payments',  $this->_pageParameters());
          $this->assign('page_forward', '<script language="JavaScript">location.href = "'.$forward_url.'";</script>');
        }
      }

      // look up some stuff regarding this btx
      $my_bao = new CRM_Banking_BAO_BankAccount();
      $my_bao->get('id', $btx_bao->ba_id);

      if ($btx_bao->party_ba_id) {
        $ba_bao = new CRM_Banking_BAO_BankAccount();
        $ba_bao->get('id', $btx_bao->party_ba_id);        
        $ba_bao->parsed = json_decode($ba_bao->data_parsed, true);        
        $contact = null;
        if ($ba_bao->contact_id) {
          $contact = civicrm_api('Contact','getsingle',array('version'=>3,'id'=>$ba_bao->contact_id));        
        }
      }
      
      // check if we are requested to run the matchers again        
      if (isset($_REQUEST['run'])) {
          // run the matchers!
          $engine = CRM_Banking_Matcher_Engine::getInstance();
          $engine->match($btx_bao);
          $btx_bao->get('id', $pid);
      }

      // parse structured data
      $this->assign('btxstatus', $choices[$btx_bao->status_id]);
      $this->assign('payment', $btx_bao);
      $this->assign('my_bao', $my_bao);
      $this->assign('party_ba', $ba_bao);
      $this->assign('contact', $contact);
      $this->assign('payment_data_raw', json_decode($btx_bao->data_raw, true));

      $a = json_decode($btx_bao->data_parsed, true);
      $a['iban'] = CRM_Banking_BAO_BankAccountReference::format('iban',$a['iban']);
      $this->assign('payment_data_parsed', $a);
      
      $extra_data = array();
      $_data_raw = json_decode($btx_bao->data_raw, true);
      if (is_array($_data_raw)) {
        $extra_data = $_data_raw;
      } else {
        $extra_data['raw'] = $btx_bao->data_raw;
      }
      if (is_array($btx_bao->getDataParsed())) $extra_data = array_merge($extra_data, $btx_bao->getDataParsed());
      $this->assign('extra_data', $extra_data);

      $this->assign('ba_data_parsed', json_decode($ba_bao->data_parsed, true));




      // check if closed ('processed' or 'ignored')
      if ($choices[$btx_bao->status_id]['name']=='processed' || $choices[$btx_bao->status_id]['name']=='ignored') {
        // this is a closed BTX, generate execution information
        $execution_info = array();
        $execution_info['status'] = $choices[$btx_bao->status_id]['name'];
        $suggestion_objects = $btx_bao->getSuggestionList();
        foreach ($suggestion_objects as $suggestion) {
          if ($suggestion->isExecuted()) {
            $execution_info['date'] = $suggestion->isExecuted();
            $execution_info['visualization'] = $suggestion->visualize_execution($btx_bao);
            break;
          }
        }
        $this->assign('execution_info', $execution_info);

        // generate message
        $execution_date = CRM_Utils_Date::customFormat($execution_info['date'], CRM_Core_Config::singleton()->dateformatFull);
        if ($choices[$btx_bao->status_id]['name']=='processed') {
          $message = sprintf(ts("This payment was <b>processed</b> on %s."), $execution_date);
        } else {
          $message = sprintf(ts("This payment was marked to be <b>ignored</b> on %s."), $execution_date);
        }
        $this->assign('status_message', $message);

      } else {
        // this is an open (new or analysed) BTX:  create suggestion list
        $suggestions = array();
        $suggestion_objects = $btx_bao->getSuggestionList();
        foreach ($suggestion_objects as $suggestion) {
          $color = $this->translateProbability($suggestion->getProbability() * 100);
            array_push($suggestions, array(
                'hash' => $suggestion->getHash(),
                'probability' => sprintf('%d&nbsp;%%', ($suggestion->getProbability() * 100)),
                'color' => $color,
                'visualization' => $suggestion->visualize($btx_bao),
                'title' => $suggestion->getTitle(),
                'actions' => $suggestion->getActions(),
            ));
        }
        $this->assign('suggestions', $suggestions);
      }

      // URLs
      $this->assign('url_run', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$pid, 'run'=>1))));
      $this->assign('url_back', banking_helper_buildURL('civicrm/banking/payments',  $this->_pageParameters()));

      if (isset($next_pid)) {
        $this->assign('url_skip_forward', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$next_pid))));
        $this->assign('url_execute', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$next_pid, 'execute'=>$pid))));

        $next_unprocessed_pid = $this->_find_next_unprocessed($list, $next_pid, $choices);
        if ($next_unprocessed_pid) {
          $this->assign('url_skip_processed', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$next_unprocessed_pid))));
        }        
      } else {
        $this->assign('url_execute', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('execute'=>$pid))));
      }

      if (isset($prev_pid)) {
        $this->assign('url_skip_back', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$prev_pid))));
      }
      $this->assign('url_show_payments', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'payments')));
      
      global $base_url;
      $this->assign('base_url',$base_url);
      parent::run();
  }





  /**
   * provides the color coding for the various probabilities
   */
  private function translateProbability( $pct ) {
    if ($pct >= 90) return '#393';
    if ($pct >= 80) return '#cc0';
    if ($pct >= 60) return '#fc3';
    if ($pct >= 30) return '#f90';
    return '#900';
  }

  /**
   * creates an array of all properties defining the current page's state
   * 
   * if $override is given, it will be taken into the array regardless
   */
  function _pageParameters($override=array()) {
    $params = array();
    if (isset($_REQUEST['id']))
        $params['id'] = $_REQUEST['id'];
    if (isset($_REQUEST['list']))
        $params['list'] = $_REQUEST['list'];
    if (isset($_REQUEST['s_list']))
        $params['s_list'] = $_REQUEST['s_list'];

    foreach ($override as $key => $value) {
        $params[$key] = $value;
    }
    return $params;
  }

  /**
   * will find the next unprocessed item in the list of remaining pids
   */
  function _find_next_unprocessed($pid_list, $next_pid, $choices) {
    // first, only query the remaining items
    $index = array_search($next_pid, $pid_list);
    $remaining_list = implode(',', array_slice($pid_list, $index));
    
    $unprocessed_states = $choices['ignored']['id'].','.$choices['processed']['id'];
    $unprocessed_sql = "SELECT id FROM civicrm_bank_tx WHERE `status_id` NOT IN ($unprocessed_states) AND `id` IN ($remaining_list)";
    $unprocessed_query = CRM_Core_DAO::executeQuery($unprocessed_sql);
    $next_unprocessed_pid = count($pid_list) + 1;
    while ($unprocessed_query->fetch()) {
      $unprocessed_id = $unprocessed_query->id;
      $new_index = array_search($unprocessed_query->id, $pid_list);
      if ($new_index < $next_unprocessed_pid) 
        $next_unprocessed_pid = $new_index;
    }

    if ($next_unprocessed_pid < count($pid_list)) {
      // this is the index of the next, unprocessed ID in list
      return $pid_list[$next_unprocessed_pid];
    } else {
      // non unprocessed pids found
      return null;
    }
  }

  /**
   * Will trigger the execution of the given suggestion (identified by its hash)
   */
  function execute_suggestion($suggestion_hash, $parameters, $btx_bao, $choices) {
    // load BTX object if not provided
    if (!$btx_bao) {
      $btx_bao = new CRM_Banking_BAO_BankTransaction();
      $btx_bao->get('id', $parameters['execute']);
    }
    $suggestion = $btx_bao->getSuggestionByHash($suggestion_hash);
    if ($suggestion) {
      // update the parameters
      $suggestion->update_parameters($parameters);
      $suggestion->execute($btx_bao);

      // create a notification bubble for the user
      $text = $suggestion->visualize_execution($btx_bao);
      if ($btx_bao->status_id==$choices['processed']['id']) {
        CRM_Core_Session::setStatus(ts("The payment was booked.")."<br/>".$text, ts("Payment closed"), 'info');
      } else {
        CRM_Core_Session::setStatus(ts("The payment was ignored.")."<br/>".$text, ts("Payment closed"), 'info');
      }
    } else {
      CRM_Core_Session::setStatus(ts("Selected suggestions disappeared. Suggestion NOT executed!"), ts("Internal Error"), 'error');
    }
  }
}
