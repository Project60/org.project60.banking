<?php


require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * The Default Options Matcher will provide the user with two default (last resort) options:
 *  1) Mark the payment as "ignored"
 *  2) Allow the manual assiciation of contributions
 */
class CRM_Banking_PluginImpl_Matcher_ExistingContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold)) $config->threshold = 0.5;
  }


  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    $threshold = $this->_plugin_config->threshold;
    $data_parsed = $btx->getDataParsed();

    // first: try to indentify the contact
    $contacts_found = array();

    // ideally, we'd use the contact identified by the bank account
    $account_contact_id = $context->getAccountContact();
    if ($account_contact_id) {
      $contacts_found[$account_contact_id] = 1.0;
    }

    // otherwise try to find matching, open contributions
    if (count($contacts_found)>=0) {
      $search_result = $context->lookupContactByName($data_parsed['name']);
      foreach ($search_result as $contact_id => $probability) {
        if ($probability > $threshold) {
          $contacts_found[$contact_id] = $probability;
        }
      }
    }

    // with the identified contacts, look up contributions
    $contributions = array();
    $contribution2contact = array();

    $status_id = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Pending');
    foreach ($contacts_found as $contact_id => $contact_probabiliy) {
      $query = array('version' => 3, 'contact_id' => $contact_id, 'contribution_status' => $status_id);
      $result = civicrm_api('Contribution', 'get', $query);
      if (isset($result['values'])) {
        foreach ($result['values'] as $contribution_id => $contribution) {
          $contribution_probability = $context->rateContribution($contribution);
          if ($contact_probabiliy * $contribution_probability > $threshold) {
            $contributions[$contribution['id']] = $contribution_probability;
            $contribution2contact[$contribution['id']] = $contact_id;
          }
        }
      }
    }

    // transform all of the contributions found into suggestions
    foreach ($contributions as $contribution_id => $contribution_probability) {
      $contact_id = $contribution2contact[$contribution_id];
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      if ($contacts_found[$contact_id]>=1.0) {
        $suggestion->addEvidence(1.0, ts("Contact was positively identified."));
      } else {
        $suggestion->addEvidence($contacts_found[$contact_id], ts("Contact was most likely identified."));
      }
      
      if ($contribution_probability>=1.0) {
        $suggestion->setTitle(ts("Matching contribution found"));
        $suggestion->addEvidence(1.0, ts("A pending contribution matching the payment was found."));
      } else {
        $suggestion->setTitle(ts("Possible matching contribution found"));
        $suggestion->addEvidence($contacts_found[$contact_id], ts("A pending contribution partially matching the paymenty was found."));
      }

      $suggestion->setId("existing-$contribution_id");
      $suggestion->setParameter('contribution_id', $contribution_id);

      // set probability manually, I think the automatic calculation provided by ->addEvidence might not be what we need here
      $suggestion->setProbability($contribution_probability*$contacts_found[$contact_id]);
      $btx->addSuggestion($suggestion);
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    // set contribution status to completed
    $completed_status = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');

    $query = array('version' => 3, 'id' => $suggestion->getParameter('contribution_id'));
    $query['contribution_status_id'] = $completed_status;
    $query['receive_date'] = date('YmdHis', strtotime($btx->booking_date));

    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution."), ts('Error'), 'error');
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
    $result = civicrm_api('Contribution', 'get', array('version' => 3, 'id' => $contribution_id));
    if (isset($result['id'])) {
      // gather information
      $contribution = $result['values'][$result['id']];
      $contact_id = $contribution['contact_id'];
      $edit_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "reset=1&action=update&context=contribution&id=$contribution_id&cid=$contact_id");
      $contact_link = CRM_Utils_System::url("civicrm/contact/view", "&reset=1&cid=$contact_id");

      // create base text
      $text = "<div>There seems to be a match:<ul>";
      foreach ($match->getEvidence() as $reason) {
        $text .= "<li>$reason</li>";
      }
      $text .= "</ul><div>";

      // add contribution summary table
      $text .= "<br/><div><table border=\"1\"><tr>";
      $text .= "<td><div class=\"btxlabel\">".ts("Donor").":&nbsp;</div><div class=\"btxvalue\"><a href=\"$contact_link\" target=\"_blank\">".$contribution['sort_name']."</td>";
      $text .= "<td><div class=\"btxlabel\">".ts("Amount").":&nbsp;</div><div class=\"btxvalue\">".$contribution['total_amount']." ".$contribution['currency']."</td>";
      $text .= "<td><div class=\"btxlabel\">".ts("Date").":&nbsp;</div><div class=\"btxvalue\">".$contribution['receive_date']."</td>";
      $text .= "<td><div class=\"btxlabel\">".ts("Type").":&nbsp;</div><div class=\"btxvalue\">".$contribution['financial_type']."</td>";
      $text .= "<td align='center'><a href=\"$edit_link\" target=\"_blank\">".ts("edit contribution")."</td>";
      $text .= "</tr></table></div>";
      return $text;
    } else {
      return ts("Internal error! Cannot find contribution #").$match->getParameter('contribution_id');
    }
  }

}

