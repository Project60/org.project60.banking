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


require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher tries to reconcile the payments with existing contributions. 
 * There are two modes:
 *   default      - matches e.g. to pending contributions and changes the status to completed
 *   cancellation - matches negative amounts to completed contributions and changes the status to cancelled
 */
class CRM_Banking_PluginImpl_Matcher_SepaMandate extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold)) $config->threshold = 0.5;
    if (!isset($config->received_date_minimum)) $config->received_date_minimum = "-10 days";
    if (!isset($config->received_date_maximum)) $config->received_date_maximum = "+10 days";
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold = $config->threshold;
    $data_parsed = $btx->getDataParsed();

    // look for the 'sepa_mandate' key
    if (empty($data_parsed['sepa_mandate'])) return null;

    // now load the mandate
    $mandate_reference = $data_parsed['sepa_mandate'];
    $mandate = civicrm_api('SepaMandate', 'getsingle', array('version'=>3, 'reference'=>$mandate_reference));
    if (!empty($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't load SEPA mandate for reference %s"), $mandate_reference), ts('Error'), 'error');
      return null;
    }

    // find the contribution
    if ($mandate['type']=='OOFF' && $mandate['entity_table']=='civicrm_contribution') {
      $contribution_id = $mandate['entity_id'];
    } elseif ($mandate['entity_table']=='civicrm_contribution_recur') {
      $contribution_recur_id = $mandate['entity_id'];
      $value_date = strtotime($btx->value_date);
      $earliest_date = date('Ymdhis', strtotime($config->received_date_minimum, $value_date));
      $latest_date = date('Ymdhis', strtotime($config->received_date_maximum, $value_date));

      $contribution_id = 0;
      $find_contribution_query = "
      SELECT  id
      FROM    civicrm_contribution
      WHERE   contribution_recur_id=$contribution_recur_id
      AND     receive_date <= '$latest_date'
      AND     receive_date >= '$earliest_date';";
      $found_contribution = CRM_Core_DAO::executeQuery($find_contribution_query);
      while ($found_contribution->fetch()) {
        if (!$contribution_id) {
          $contribution_id = $found_contribution->id;
        } else {
          // this is the second contribution found!
          CRM_Core_Session::setStatus(ts("There was more than one matching contribution found! Try to configure the plugin with a smaller search time span."), ts('Error'), 'error');
          return null;
        }
      }

      if (!$contribution_id) {
        // no contribution found
        CRM_Core_Session::setStatus(ts("There was no matching contribution! Try to configure the plugin with a larger search time span."), ts('Error'), 'error');
        return null;        
      }

    } else {
      error_log("org.project60.sepa: matcher_sepa: Bad mandate type.");
      return null;
    }

    // finally: create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setParameter('contribution_id', $contribution_id);
    $suggestion->setParameter('mandate_id', $mandate['id']);
    $suggestion->setParameter('mandate_reference', $mandate_reference);
    $suggestion->setProbability(1.0);
    $suggestion->setTitle(ts("SEPA SDD Payment"));
    $btx->addSuggestion($suggestion);

    return $this->_suggestions;
  }

  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    $contribution_id = $suggestion->getParameter('contribution_id');

    $query = array('version' => 3, 'id' => $contribution_id);
    $query['contribution_status_id'] = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');
    $query['receive_date'] = date('Ymdhis', strtotime($btx->value_date));
    $query = array_merge($query, $this->getPropagationSet($btx, 'contribution'));   // add propagated values
    $result = civicrm_api('Contribution', 'create', $query);

    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution."), ts('Error'), 'error');
    } else {
      // everything seems fine, save the account
      if (!empty($result['values'][$contribution_id]['contact_id'])) {
        $this->storeAccountWithContact($btx, $result['values'][$contribution_id]['contact_id']);
      } elseif (!empty($result['values'][0]['contact_id'])) {
        $this->storeAccountWithContact($btx, $result['values'][0]['contact_id']);
      }

      // close transaction group if this was the last transaction
      // TODO
    }

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    // NOTHING to do...
  }

    /** 
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    // load the contribution
    $contribution_id = $match->getParameter('contribution_id');
    $mandate_id = $match->getParameter('mandate_id');
    $mandate_reference = $match->getParameter('mandate_reference');

    $result = civicrm_api('Contribution', 'get', array('version' => 3, 'id' => $contribution_id));
    if (isset($result['id'])) {
      // gather information
      $contribution = $result['values'][$result['id']];
      $contact_id = $contribution['contact_id'];
      $contact_link = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid=$contact_id");
      $contact_html = "<a href=\"$contact_link\" target=\"_blank\">".$contribution['display_name']."</a>";
      $mandate_link = CRM_Utils_System::url("civicrm/sepa/xmandate", "mid=$mandate_id");
      $contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "reset=1&id=$contribution_id&cid=$contact_id&action=view");

      // create text
      $text = "<div><p>";
      $text .= sprintf(ts("This payment is a SEPA direct debit contribution by %s."), $contact_html)." ";
      $text .= sprintf(ts("The mandate reference is <a href=\"%s\" target=\"_blank\">%s</a>."), $mandate_link, $mandate_reference)." ";
      $text .= "</p><p>";
      $text .= sprintf(ts("Contribution <a href=\"%s\" target=\"_blank\">[%s]</a> will be closed, as will be the sepa transaction group if this is the last contribution."), $contribution_link, $contribution_id)." ";
      $text .= "</p></div>";
      return $text;
    } else {
      return ts("Internal error! Cannot find contribution #").$match->getParameter('contribution_id');
    }
  }

  /** 
   * Generate html code to visualize the executed match.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    $contribution_id = $match->getParameter('contribution_id');
    $contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id=${contribution_id}&cid=2&context=home");
    return "<p>".sprintf(ts("This SEPA payment was associated with <a href=\"%s\">contribution #%s</a>."), $contribution_link, $contribution_id)."</p>";
  }
}

