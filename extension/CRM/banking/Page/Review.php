<?php

require_once 'CRM/Core/Page.php';
require_once 'CRM/Core/Page.php';
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


      // check if we are requested to run the matchers again        
      if (isset($_REQUEST['run'])) {
          // run the matchers!
          $engine = CRM_Banking_Matcher_Engine::getInstance();
          $engine->match($btx_bao);
          $btx_bao->get('id', $pid);
      }

      // parse structured data
      $this->assign('payment', $btx_bao);
      $this->assign('payment_data_parsed', json_decode($btx_bao->data_parsed, true));

      // create suggestion list
      $suggestions = array();
      $suggestion_objects = $btx_bao->getSuggestionList();
      foreach ($suggestion_objects as $suggestion) {
          array_push($suggestions, array(
              'probability' => sprintf('%d %%', ($suggestion->getProbability() * 100)),
              'visualization' => $suggestion->visualize($btx_bao),
              'title' => $suggestion->getTitle(),
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

      parent::run();
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
