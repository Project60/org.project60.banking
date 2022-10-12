<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

/**
 * The Default Options Matcher will provide the user with two default (last resort) options:
 *  1) Mark the payment as "ignored"
 *  2) Allow the manual assiciation of contributions
 */
class CRM_Banking_PluginImpl_Matcher_DefaultOptions extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->manual_enabled)) $config->manual_enabled = true;
    if (!isset($config->manual_probability)) $config->manual_probability = 0.1;
    if (!isset($config->manual_show_always)) $config->manual_show_always = true;
    if (!isset($config->lookup_contact_by_name)) $config->lookup_contact_by_name = array('soft_cap_probability' => 0.8, 'soft_cap_min' => 10, 'hard_cap_probability' => 0.4);
    if (!isset($config->manual_title)) $config->manual_title = "Manually processed.";
    if (!isset($config->manual_message)) $config->manual_message = "Please configure";
    if (!isset($config->manual_default_source)) $config->manual_default_source = "";
    if (!isset($config->manual_contribution)) $config->manual_contribution = "Contribution:";
    if (!isset($config->contribution_id_injection)) $config->contribution_id_injection = '';  // parameter name containing comma separated list to be added to the manually matched contributions
    if (!isset($config->manual_default_contacts)) $config->manual_default_contacts = array(); // contacts to always be added to the list (contact_id => probability)
    if (!isset($config->default_financial_type_id)) $config->default_financial_type_id = 1;
    if (!isset($config->createnew_value_propagation)) $config->createnew_value_propagation = array();
    if (!isset($config->manual_default_financial_type_id)) $config->manual_default_financial_type_id = NULL;

    if (!isset($config->ignore_enabled)) $config->ignore_enabled = true;
    if (!isset($config->ignore_probability)) $config->ignore_probability = 0.1;
    if (!isset($config->ignore_show_always)) $config->ignore_show_always = true;
    if (!isset($config->ignore_title)) $config->ignore_title = "Not Relevant";
    if (!isset($config->ignore_message)) $config->ignore_message = "Please configure";
  }

  function autoExecute() {
    // NO autoexec for this matcher
    return false;
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    $config = $this->_plugin_config;

    // create 'manually processed' suggestion, if applicable
    if ($config->manual_enabled) {
      if ($config->manual_show_always || $this->has_other_suggestions($btx)) {
        $manually_processed = new CRM_Banking_Matcher_Suggestion($this, $btx);
        $manually_processed->setProbability($this->get_probability($config->manual_probability, $btx));
        $manually_processed->setTitle($config->manual_title);
        $manually_processed->setId('manual');

        // find related contacts
        $data_parsed = $btx->getDataParsed();
        $contacts = $context->findContacts(0, $data_parsed['name'], $config->lookup_contact_by_name);

        // add default contacts
        foreach ($config->manual_default_contacts as $contact_id => $probability) {
          if (isset($contacts[$contact_id])) {
            // only override probability if it would be improved
            $contacts[$contact_id] = max($probability, $contacts[$contact_id]);
          } else {
            // not set yet, add to list
            $contacts[$contact_id] = $probability;
          }
        }

        // add result to parameters
        $manually_processed->setParameter('contact_ids', implode(',', array_keys($contacts)));
        $manually_processed->setParameter('contact_ids2probability', json_encode($contacts));

        // add injected contributions
        if ($config->contribution_id_injection && !empty($data_parsed[$config->contribution_id_injection])) {
          $manually_processed->setParameter('injected_contribution_ids', $data_parsed[$config->contribution_id_injection]);
        }

        $btx->addSuggestion($manually_processed);
      }
    }

    // create 'not relevant' suggestion, if applicable
    if ($config->ignore_enabled) {
      if ($config->ignore_show_always || $this->has_other_suggestions($btx)) {
        $not_relevant = new CRM_Banking_Matcher_Suggestion($this, $btx);
        $not_relevant->addEvidence($this->get_probability($config->ignore_probability, $btx));
        $not_relevant->setTitle($config->ignore_title);
        $not_relevant->setId('ignore');
        $btx->addSuggestion($not_relevant);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  /**
   * Execute the previously generated suggestion,
   *   and close the transaction
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   *   the suggestion to be executed
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   */
  public function execute($suggestion, $btx) {
    if ($suggestion->getId()==="manual") {
      $cids = $suggestion->getParameter('contribution_ids');
      $contribution_count = 0;
      if ($cids) {
        $completed_status = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');
        $cancelled_status = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Cancelled');

        foreach ($cids as $cid) {
          if ($cid) {
            $contribution = civicrm_api('Contribution', 'getsingle', array('version' => 3, 'id' => $cid));
            if (!empty($contribution['is_error'])) {
              CRM_Core_Session::setStatus(sprintf(E::ts("Couldn't find contribution #%s"), $cid), E::ts('Error'), 'error');
              continue;
            }

            // save the account
            if (!empty($contribution['contact_id'])) {
              $this->storeAccountWithContact($btx, $contribution['contact_id']);
            }

            $query = array('version' => 3, 'id' => $cid);
            $query['is_test'] = 0;
            $query = array_merge($query, $this->getPropagationSet($btx, $suggestion, 'contribution'));   // add propagated values

            // set status to completed, unless it's a negative amount...
            if ($btx->amount < 0) {
              // ...in this case, we want to cancel this
              $query['contribution_status_id'] = $cancelled_status;
              $query['cancel_date'] = date('YmdHis', strtotime($btx->booking_date));
            } else {
              // ...otherwise, we close it
              $query['contribution_status_id'] = $completed_status;
              $query['receive_date'] = date('YmdHis', strtotime($btx->booking_date));
            }

            CRM_Banking_Helpers_IssueMitigation::mitigate358($query);
            $result = civicrm_api('Contribution', 'create', $query);
            if (isset($result['is_error']) && $result['is_error']) {
              CRM_Core_Session::setStatus(sprintf(E::ts("Couldn't modify contribution #%s"), $cid), E::ts('Error'), 'error');
              return NULL;
            } else {
              $contribution_count += 1;
            }

            // link the contribution
            CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $cid);
          }
        }

        if ($contribution_count > 0) {
          $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
          $btx->setStatus($newStatus);
          parent::execute($suggestion, $btx);
        } else {
          CRM_Core_Session::setStatus(E::ts("The contribution is not valid. The transaction is NOT completed."), E::ts('Transaction NOT completed.'), 'alert');
          return NULL;
        }

      } else {
        CRM_Core_Session::setStatus(E::ts("No contribution given. The transaction is NOT completed."), E::ts('Transaction NOT completed.'), 'alert');
        return NULL;
      }
    } else {
      // this is the IGNORE action. Simply set the status to ignored
      $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Ignored');
      $btx->setStatus($newStatus);
      parent::execute($suggestion, $btx);
    }
    return TRUE;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    if ($match->getId() === "manual") {
      if (isset($parameters["manual_match_contributions"])) {
        $contributions = explode(",", $parameters["manual_match_contributions"]);
        $match->setParameter('contribution_ids', $contributions);
      }
    }
  }

    /**
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {

    $smarty_vars = array();

    $btx_data = array();
    CRM_Core_DAO::storeValues($btx, $btx_data);

    $smarty_vars['btx'] =                            $btx_data;
    $smarty_vars['mode'] =                           $match->getId();
    $smarty_vars['contact_ids'] =                    $match->getParameter('contact_ids');
    $smarty_vars['contact_ids2probability'] =       $match->getParameter('contact_ids2probability');
    $smarty_vars['injected_contribution_ids'] =      $match->getParameter('injected_contribution_ids');
    $smarty_vars['ignore_message'] =                 $this->_plugin_config->ignore_message;
    $smarty_vars['booking_date'] =                   date('YmdHis', strtotime($btx->booking_date));
    $smarty_vars['status_pending'] =                 banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Pending');
    $smarty_vars['manual_default_source'] =          $this->_plugin_config->manual_default_source;
    $smarty_vars['manual_default_financial_type_id']=$this->_plugin_config->manual_default_financial_type_id;
    $smarty_vars['create_propagation'] =             $this->getPropagationSet($btx, $match, 'contribution', $this->_plugin_config->createnew_value_propagation);


    // the behaviour for Contribution.get has changed in a weird way with 4.7
    if (version_compare(CRM_Utils_System::version(), '4.7', '>=')) {
      $smarty_vars['manual_contribution_get_return_params'] = "contact,financial_type";
    } else {
      $smarty_vars['manual_contribution_get_return_params'] = "contact_id,financial_type,contact,display_name,receive_date,contribution_status,total_amount,currency";
    }

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/DefaultOptions.suggestion.tpl');
    $smarty->popScope();

    return $html_snippet;
  }

  /**
   * Generate html code to visualize the executed match.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    if ($match->getId()==="manual") {
      $cids = $match->getParameter('contribution_ids');
      $text = "<p>".E::ts("This transaction was manually matched to the following contributions:")."<ul>";
      foreach ($cids as $contribution_id) {
        if ($contribution_id) {
          $contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id=$contribution_id&cid=2&context=home");
          $text .= "<li><a href=\"$contribution_link\">".E::ts("Contribution")." #$contribution_id</a>";
        }
      }
      $text .=  "</ul>";
      return $text;
    }
  }

  /**
   * check if there are more suggestions for this transaction
   */
  private function has_other_suggestions(CRM_Banking_BAO_BankTransaction $btx) {
    return count($btx->getSuggestions())>0;
  }

  /**
   * calculate the absolute probability based on the (possibly) relative value in the config
   */
  private function get_probability($string_value, CRM_Banking_BAO_BankTransaction $btx) {
    if (substr($string_value, -1) === "%") {
      // if the value ends in '%' it's meant to be relative to the least probable suggestion
      $suggestion_list = $btx->getSuggestionList();
      $least_probable = end($suggestion_list);
      if ($least_probable) {
        $least_probable_value = $least_probable->getProbability();
      } else {
        $least_probable_value = 1;
      }
      return $least_probable_value * substr($string_value, 0, strlen($string_value)-1) / 100.0;

    } else {
      // in the default case, we just assume it's an absolute value anyways...
      return $string_value;
    }
  }
}

