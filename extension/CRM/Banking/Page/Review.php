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

            if (isset($_REQUEST['hash'])) {
        $hash = $_REQUEST['hash'];
        foreach ($btx_bao->getSuggestionList() as $suggestion) {
          if ($suggestion->hash == $hash) {
            $suggestion->execute($btx_bao);
            $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status','Processed');
            $btx_bao->setStatus( $newStatus );
            $btx_bao->status_id = $newStatus;
            $btx_bao->resetSuggestions();
          }
        }
      }
      
      $my_bao = new CRM_Banking_BAO_BankAccount();
      $my_bao->get('id', $btx_bao->ba_id);        

      if ($btx_bao->party_ba_id) {
        $ba_bao = new CRM_Banking_BAO_BankAccount();
        $ba_bao->get('id', $btx_bao->party_ba_id);        
        $ba_bao->parsed = json_decode($ba_bao->data_parsed, true);        
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
      $choices = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
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

      // create suggestion list
      $suggestions = array();
      $suggestion_objects = $btx_bao->getSuggestionList();
      foreach ($suggestion_objects as $suggestion) {
        $color = $this->translateProbability($suggestion->getProbability() * 100);
          array_push($suggestions, array(
              'hash' => $suggestion->hash,
              'probability' => sprintf('%d %%', ($suggestion->getProbability() * 100)),
              'color' => $color,
              'visualization' => $suggestion->visualize($btx_bao),
              'title' => $suggestion->getTitle(),
              'actions' => $suggestion->getActions(),
          ));
      }
      $this->assign('suggestions', $suggestions);

      // URLs

      $this->assign('url_run', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$pid, 'run'=>1))));
      $this->assign('url_back', banking_helper_buildURL('civicrm/banking/payments',  $this->_pageParameters()));

      if (isset($next_pid)) {
        $this->assign('url_skip_forward', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$next_pid))));
      }

      if (isset($prev_pid)) {
        $this->assign('url_skip_back', banking_helper_buildURL('civicrm/banking/review',  $this->_pageParameters(array('id'=>$prev_pid))));
      }
      $this->assign('url_show_payments', banking_helper_buildURL('civicrm/banking/payments', array('show'=>'payments')));
      
      global $base_url;
      $this->assign('base_url',$base_url);
      parent::run();
  }

  
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

    foreach ($override as $key => $value) {
        $params[$key] = $value;
    }
    return $params;
  }
}
