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

require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher tries to reconcile the payments with existing memberships.
 */
class CRM_Banking_PluginImpl_Matcher_RecurringContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))                     $config->threshold = 0.5;
    if (!isset($config->search_terms))                  $config->search_terms = array();
    if (!isset($config->search_wo_contacts))            $config->search_wo_contacts = FALSE;  // if true, the matcher will keep searching (using the search_terms) even if no contacts are found
    if (!isset($config->multimatch))                    $config->multimatch = FALSE;     // if true, the matcher will also try and match a multitude of recurring contributions
    if (!isset($config->multimatch_cutoff))             $config->multimatch_cutoff = 30; // max number of multimatches (the number grows exponentially).
    if (!isset($config->contact_id_list))               $config->contact_id_list = '';  // if not empty, take contacts from comma separated list instead of contact search
    if (!isset($config->created_contribution_status))   $config->created_contribution_status = 'Completed';
    if (!isset($config->recurring_contribution_status)) $config->recurring_contribution_status = array('Pending', 'In Progress');
    if (!isset($config->suggestion_title))              $config->suggestion_title = '';
    if (!isset($config->recurring_mode))                $config->recurring_mode = 'static'; // see getExpectedDate()

    // amount check / amount penalty
    if (!isset($config->amount_check))                  $config->amount_check = "1";
    if (!isset($config->amount_relative_minimum))       $config->amount_relative_minimum = 1.0;
    if (!isset($config->amount_relative_maximum))       $config->amount_relative_maximum = 1.0;
    if (!isset($config->amount_absolute_minimum))       $config->amount_absolute_minimum = 0;
    if (!isset($config->amount_absolute_maximum))       $config->amount_absolute_maximum = 1;
    if (!isset($config->amount_penalty))                $config->amount_penalty = 1.0;

    if (!isset($config->request_amount_confirmation))  $config->request_amount_confirmation = FALSE;   // if true, user confirmation is required to reconcile differing amounts

    // date check / date range
    if (!isset($config->received_date_check))           $config->received_date_check = "1";  // WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW
    if (!isset($config->acceptable_date_offset_from))   $config->acceptable_date_offset_from = "-1 days";
    if (!isset($config->acceptable_date_offset_to))     $config->acceptable_date_offset_to = "+1 days";
    if (!isset($config->date_offset_minimum))           $config->date_offset_minimum = "-5 days";
    if (!isset($config->date_offset_maximum))           $config->date_offset_maximum = "+15 days";
    if (!isset($config->date_penalty))                  $config->date_penalty = 0.5;

    // other checks
    if (!isset($config->payment_instrument_penalty))    $config->payment_instrument_penalty = 0.0;
    if (!isset($config->currency_penalty))              $config->currency_penalty = 0.5;

    // check existing payments
    if (!isset($config->existing_check))                $config->existing_check = "1";
    if (!isset($config->existing_penalty))              $config->existing_penalty = 0.3;
    if (!isset($config->existing_status_list))          $config->existing_status_list = [1,2];
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config      = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $data_parsed = $btx->getDataParsed();
    $penalty     = $this->getPenalty($btx);

    // find potential contacts
    $contactID2probability = array();
    if (empty($config->contact_id_list)) {
      $nameSearch = $context->findContacts($threshold, $data_parsed['name'], $config->lookup_contact_by_name);
      foreach ($nameSearch as $contact_id => $probability) {
        if (($probability - $penalty) < $threshold) continue;
        $contactID2probability[$contact_id] = $probability;
      }
    } else {
      if (!empty($data_parsed[$config->contact_id_list])) {
        $id_list = explode(',', $data_parsed[$config->contact_id_list]);
        foreach ($id_list as $contact_id) {
          $contact_id = (int) $contact_id;
          if ($contact_id > 0) {
            $contactID2probability[$contact_id] = 1.0;
          }
        }
      }
    }

    // create suggestions
    if (!empty($config->search_terms)) {
      $query = $this->getPropagationSet($btx, NULL, '', $config->search_terms);
    } else {
      $query = array();
    }
    if ($config->search_wo_contacts) {
      $suggestions = $this->createRecurringContributionSuggestions($query, 1.0, $btx, $context);
    } else {
      $suggestions = array();
      foreach ($contactID2probability as $contact_id => $probability) {
        $query['contact_id'] = $contact_id;
        $new_suggestions = $this->createRecurringContributionSuggestions($query, $probability, $btx, $context);
        $suggestions = array_merge($suggestions, $new_suggestions);
      }
    }

    // apply penalties and threshold
    foreach ($suggestions as $suggestion) {
      $probability = $suggestion->getProbability();
      $probability -= $penalty;
      if ($probability >= $threshold) {
        if ($penalty) {
          $suggestion->addEvidence($penalty, E::ts("A general penalty was applied."));
        }
        $suggestion->setProbability($probability);
        $btx->addSuggestion($suggestion);
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
    $config      = $this->_plugin_config;
    $smarty_vars = array();

    // load the recurring contribution(s)
    $rcontribution_id_list = $suggestion->getParameter('recurring_contribution_ids');
    $rcontribution_ids = explode(',', $rcontribution_id_list);
    $rcontributions = array();
    foreach ($rcontribution_ids as $rcontribution_id) {
      $rcontributions[] = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
    }
    $virtual_rcur = $this->createVirtualRecurringContribution($rcontributions);

    // verify expected amount
    $recorded_amount = $suggestion->getParameter('expected_amount');
    if ($virtual_rcur['amount'] != $recorded_amount) {
      CRM_Core_Session::setStatus(E::ts('The expected amount for the recurring contribution(s) seems to have changed. Please analyse transaction again.'), E::ts('Recurring contribution changed'), 'alert');
      return false;
    }

    // verify due date
    $last_contribution = NULL;
    $recorded_due_date = $suggestion->getParameter('expected_date');
    $due_date = $this->getExpectedDate($virtual_rcur, $btx, $config->recurring_mode, $last_contribution);
    if ($due_date) {
      $current_due_date = date('Y-m-d', $due_date);
    } else {
      $current_due_date = E::ts('None');
    }
    if ($recorded_due_date != $current_due_date) {
      // something changed...
      CRM_Core_Session::setStatus(E::ts('The situation for the recurring contribution seems to have changed. Please analyse transaction again.'), E::ts('Recurring contribution changed'), 'alert');
      return false;
    }

    // go ahead and create the contributions
    $contribution_ids = array();
    if (count($rcontributions) == 1) {
      $rcontribution = $rcontributions[0];
      $contribution = array();
      $contribution['contact_id']                 = $suggestion->getParameter('contact_id');
      $contribution['total_amount']               = $btx->amount;
      $contribution['receive_date']               = $btx->booking_date;
      $contribution['currency']                   = $btx->currency;
      $contribution['financial_type_id']          = CRM_Utils_Array::value('financial_type_id', $rcontribution);
      $contribution['payment_instrument_id']      = CRM_Utils_Array::value('payment_instrument_id', $rcontribution);
      $contribution['campaign_id']                = CRM_Utils_Array::value('campaign_id', $rcontribution);
      $contribution['contribution_recur_id']      = $rcontribution_id;
      $contribution['contribution_status_id']     = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $config->created_contribution_status);
      $contribution = array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));
      $contribution['version'] = 3;
      $result = civicrm_api('Contribution', 'create', $contribution);
      if (isset($result['is_error']) && $result['is_error']) {
        CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.")."<br/>".E::ts("Error was: ").$result['error_message'], E::ts('Error'), 'error');
        return false;
      }
      $contribution_ids[] = $result['id'];
      $suggestion->setParameter('contribution_id', $result['id']);
    } else {
      foreach ($rcontributions as $rcontribution) {
        $contribution = array();
        $contribution['contact_id']                 = $rcontribution['contact_id'];
        $contribution['total_amount']               = $rcontribution['amount'];
        $contribution['receive_date']               = $btx->booking_date;
        $contribution['currency']                   = $rcontribution['currency'];
        $contribution['financial_type_id']          = CRM_Utils_Array::value('financial_type_id', $rcontribution);
        $contribution['payment_instrument_id']      = CRM_Utils_Array::value('payment_instrument_id', $rcontribution);
        $contribution['campaign_id']                = CRM_Utils_Array::value('campaign_id', $rcontribution);
        $contribution['contribution_recur_id']      = $rcontribution['id'];
        $contribution['contribution_status_id']     = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $config->created_contribution_status);
        $contribution = array_merge($contribution, $this->getPropagationSet($btx, $suggestion, 'contribution'));
        $contribution['version'] = 3;
        $result = civicrm_api('Contribution', 'create', $contribution);
        if (isset($result['is_error']) && $result['is_error']) {
          CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.")."<br/>".E::ts("Error was: ").$result['error_message'], E::ts('Error'), 'error');
          return false;
        }
        $contribution_ids[] = $result['id'];
      }
    }

    // success!
    $contribution_id_list = implode(',', $contribution_ids);
    $suggestion->setParameter('contribution_ids', $contribution_id_list);

    // link contributions to tx
    foreach ($contribution_ids as $contribution_id) {
      CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution_id);
    }

    // save the account
    $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return true;
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
    $config      = $this->_plugin_config;
    $smarty_vars = array();

    // load the recurring contribution
    $rcontributions = array();
    $contacts = array();

    $rcontribution_ids = $match->getParameter('recurring_contribution_ids');
    $rcontribution_ids = explode(',', $rcontribution_ids);
    foreach ($rcontribution_ids as $rcontribution_id) {
      // load recurring contribution
      $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));

      // load contact
      $contact_id = $rcontribution['contact_id'];
      if (empty($contact[$contact_id])) {
        $contacts[$contact_id] = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
      }

      $last_contribution = NULL;
      $due_date = $this->getExpectedDate($rcontribution, $btx, $config->recurring_mode, $last_contribution);
      if (empty($due_date)) {
        $rcontribution['due_date'] = '';
      } else {
        $rcontribution['due_date'] = date('Ymdhis', $due_date);
      }
      $rcontribution['last_contribution'] = $last_contribution;

      $rcontributions[$rcontribution_id] = $rcontribution;
    }

    // get due date:
    $due_date = $this->getExpectedDate($rcontribution, $btx, $config->recurring_mode, $last_contribution);

    // assign to smarty and compile HTML
    $smarty_vars['recurring_contributions'] = $rcontributions;
    $smarty_vars['contacts']                = $contacts;
    $smarty_vars['penalties']               = $match->getEvidence();

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/RecurringContribution.suggestion.tpl');
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
    $contribution_id_list = $match->getParameter('contribution_ids');
    if ($contribution_id_list == NULL) {
      // legacy
      $contribution_id_list = $match->getParameter('contribution_id');
    }

    $contribution_ids = explode(',', $contribution_id_list);
    $contributions = array();
    foreach ($contribution_ids as $contribution_id) {
      if (empty($contribution_id)) continue;
      $contributions[] = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    }

    // just assign to smarty and compile HTML
    $smarty_vars = array();
    $smarty_vars['contributions'] = $contributions;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/RecurringContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }




  /*************************************************************
   *                    HELPER FUNCTIONS                      **
   *************************************************************/


  /**
   * create suggestions for matching recurring contributions
   */
  function createRecurringContributionSuggestions($query, $base_probability, $btx, $context) {
    $config      = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $data_parsed = $btx->getDataParsed();
    $suggestions = array();
    // don't waste your time contacts below the threshold...
    if ($base_probability < $threshold) return $suggestions;

    $recurring_contributions = $this->findCandidates($query, $config);
    foreach ($recurring_contributions as $rcur) {
      $probability = $base_probability;
      $rcur_id = $rcur['id'];

      // find the next expected date for the recurring contribution
      $expected_date = $this->getExpectedDate($rcur, $btx, $config->recurring_mode);
      if ($expected_date==NULL) continue;

      // create a suggestion
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setId("recurring-$rcur_id");
      $suggestion->setParameter('recurring_contribution_ids', $rcur_id);
      $suggestion->setParameter('contact_id', $rcur['contact_id']);
      $suggestion->setParameter('expected_date', date('Y-m-d', $expected_date));
      $suggestion->setParameter('expected_amount', $rcur['amount']);
      if (!empty($config->suggestion_title)) {
        $suggestion->setTitle($config->suggestion_title);
      }
      if ($probability<1.0) {
        $suggestion->addEvidence(1.0-$probability, E::ts("The contact could not be uniquely identified."));
      }
      if ($config->request_amount_confirmation) {
        if ($btx->amount != $rcur['amount']) {
          $suggestion->setUserConfirmation(E::ts("The reconciled amount of this suggestion would differ from the transaction amount. Do you want to continue anyway?"));
        }
      }

      // RCUR LOOP: CHECK AMOUNT
      if ($config->amount_check) {
        // calculate the amount penalties (equivalent to CRM_Banking_PluginImpl_Matcher_ExistingContribution)
        $transaction_amount = $btx->amount;
        $expected_amount = $rcur['amount'];

        $amount_delta = $transaction_amount - $expected_amount;
        if (   ($transaction_amount < ($expected_amount * $config->amount_relative_minimum))
            && ($amount_delta < $config->amount_absolute_minimum)) continue;
        if (   ($transaction_amount > ($expected_amount * $config->amount_relative_maximum))
            && ($amount_delta > $config->amount_absolute_maximum)) continue;

        $amount_range_rel = $transaction_amount * ($config->amount_relative_maximum - $config->amount_relative_minimum);
        $amount_range_abs = $config->amount_absolute_maximum - $config->amount_absolute_minimum;
        $amount_range = max($amount_range_rel, $amount_range_abs);

        if ($amount_range) {
          $penalty = $config->amount_penalty * (abs($amount_delta) / $amount_range);
          if ($penalty) {
            $suggestion->addEvidence($penalty, E::ts("The amount of the transaction differs from the expected amount."));
            $probability -= $penalty;
          }
        }
      }

      // RCUR LOOP: CHECK CURRENCY
      if ($context->btx->currency != $rcur['currency']) {
        $suggestion->addEvidence($config->currency_penalty, E::ts("The currency of the transaction is not as expected."));
        $probability -= $config->currency_penalty;
      }

      // RCUR LOOP: CHECK EXPECTED DATE
      if ($config->received_date_check) {
        // use date only
        $transaction_date = strtotime(date('Y-m-d', strtotime($context->btx->value_date)));

        // only apply penalties, if the offset is outside the accepted range
        $date_offset = $transaction_date - $expected_date;
        if ( $date_offset < strtotime($config->acceptable_date_offset_from, 0)
          || $date_offset > strtotime($config->acceptable_date_offset_to, 0)) {

          // check if the payment is completely out of bounds
          if ($date_offset < strtotime($config->date_offset_minimum, 0)) continue;
          if ($date_offset > strtotime($config->date_offset_maximum, 0)) continue;

          // calculate the date penalties
          $date_range =   (strtotime($config->date_offset_maximum) - strtotime($config->date_offset_minimum))
                         -(strtotime($config->acceptable_date_offset_to) - strtotime($config->acceptable_date_offset_from));
          if ($date_offset < 0) {
            $date_delta = abs($date_offset - strtotime($config->acceptable_date_offset_from, 0));
          } else {
            $date_delta = abs($date_offset - strtotime($config->acceptable_date_offset_to, 0));
          }

          if ($date_range) {
            $penalty = $config->date_penalty * ($date_delta / $date_range);
            if ($penalty) {
              $suggestion->addEvidence($penalty, E::ts("The date of the transaction deviates too much from the expected date."));
              $probability -= $penalty;
            }
          }
        }
      }

      // RCUR LOOP: CHECK FOR OTHER PAYMENTS
      if ($config->existing_check) {
        $other_contributions_id_list = array();
        $expected_date_string = date('Y-m-d', $expected_date);
        if (empty($config->existing_status_list)) $config->existing_status_list = array(1,2);
        $existing_status_list = implode(',', $config->existing_status_list);

        // determine date range
        $date_offset_minimum = str_replace('DAYS', 'DAY', strtoupper($config->date_offset_minimum));
        $date_offset_maximum = str_replace('DAYS', 'DAY', strtoupper($config->date_offset_maximum));

        $sql = "
        SELECT id AS contribution_id
        FROM civicrm_contribution
        WHERE contribution_recur_id IN ($rcur_id)
          AND contribution_status_id IN ($existing_status_list)
          AND (receive_date BETWEEN ('$expected_date_string' + INTERVAL $date_offset_minimum)
                                AND ('$expected_date_string' + INTERVAL $date_offset_maximum) );";
        $sql_query = CRM_Core_DAO::executeQuery($sql);
        while ($sql_query->fetch()) {
          $other_contributions_id_list[] = $sql_query->contribution_id;
        }

        if (!empty($other_contributions_id_list)) {
          $links = array();
          if (count($other_contributions_id_list) == 1) {
            $message = E::ts("There is already another contribution recorded for this interval: ");
          } else {
            $message = E::ts("There are already multiple contributions recorded for this interval: ");
          }
          foreach ($other_contributions_id_list as $other_contributions_id) {
            $links[] = "<a href='" . CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&id=$other_contributions_id&cid={$rcur['contact_id']}&action=view") . "'>[$other_contributions_id]</a>";
          }
          $message .= implode(', ', $links);
          $suggestion->addEvidence($config->existing_penalty, $message);
          $probability -= $config->existing_penalty;
        }
      }

      $suggestion->setProbability($probability);
      $suggestions[] = $suggestion;
    } // END RCUR LOOP

    return $suggestions;
  }


  /**
   * Try to find the next expected date for the given
   * recurring contribution. The behaviour depends on the
   * $config->recurring_mode setting:
   *  'static': expects the contribution with a fixed cycle, i.e. start with
   *            the first cycle day after start_date, and the on that day
   *            for each cycle
   *  'adapt':  expects the contribution one cycle after the last recorded payment.
   *            that means, that if one payment is late, the next payment is
   *            expected on the next cycle day one cycle after the last one
   *            (or the start date in case of the first payment)
   *  'float':  expects the contribution exactly one cycle after the last recorded payment.
   *            that means, that the cycle day is ignored
   *  'total':  expects the next payment on the date according to the static calculation
   *            and the total of the existing payments.
   *            Example 1: monthly recurring contribution over 10€, but donor paid 30€ in
   *            the first installment => next expected date is only 3 months later
   *            Example 2: same recurring contribution as example 1, but donor paid 10€, then
   *            nothing, than another 10€ => next expected date is NOT 1 month after the last, but
   *            two months after start_date, since there's not enough money.
   *            if the calculated due date is before the current transaction date, it will return the
   *            transactions's date, so that any payment will be accepted, even if too late.
   *  (more to come...?)
   */
  public function getExpectedDate(&$rcontribution, &$btx, $recurring_mode, &$last_contribution = NULL) {
    // virtual recurring contributions require special treatment:
    if (isset($rcontribution['virtual'])) {
      return $this->getExpectedDateVirtual($rcontribution, $btx, $recurring_mode, $last_contribution);
    }

    // find a maximum
    $max_date = strtotime("+1 year");
    if (!empty($rcontribution['end_date']) && strtotime($rcontribution['end_date'])<$max_date) {
      $max_date = strtotime($rcontribution['end_date']);
    }
    if (!empty($rcontribution['cancel_date']) && strtotime($rcontribution['cancel_date'])<$max_date) {
      $max_date = strtotime($rcontribution['cancel_date']);
    }

    // these modes require the list of recurring contributions
    $contribution_query = civicrm_api3('Contribution', 'get', array(
      'contribution_recur_id' => $rcontribution['id'],
      'options'               => array('limit' => 9999),
      ));

    // find the last contribution
    $contributions = $contribution_query['values'];
    $total_amount      = 0.0;
    $last_contribution = NULL;
    foreach ($contributions as $contribution_id => $contribution) {
      if ($contribution['contribution_status_id'] == 1) {
        $total_amount += $contribution['total_amount'];
      }
      if (empty($contribution['receive_date'])) continue;
      if ($last_contribution==NULL) {
        $last_contribution = $contribution;
        continue;
      }
      if (strtotime($last_contribution['receive_date']) < strtotime($contribution['receive_date'])) {
        $last_contribution = $contribution;
      }
    }

    // get some values
    $cycle_day   = $rcontribution['cycle_day'];
    $interval    = $rcontribution['frequency_interval'];
    $unit        = $rcontribution['frequency_unit'];
    $target_date = strtotime($btx->booking_date);


    if ($recurring_mode == 'static') {
      $start_date = strtotime(date('Y-m-d', strtotime($rcontribution['start_date'])));
      $next_date =  mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));

      $closest_date = $next_date;
      while ( $next_date <= $max_date) {
        if (abs($next_date-$target_date) <= abs($closest_date-$target_date)) {
          $closest_date = $next_date;
          $next_date = strtotime("+$interval $unit", $next_date);
        } else {
          // once we're not improving any more, we can stop
          return $closest_date;
        }
      }


    } elseif ($recurring_mode == 'adapt') {
      if ($last_contribution==NULL) {
        // this relies on the last contribution
        return $this->getExpectedDate($rcontribution, $btx, 'static');
      }
      $last = strtotime($last_contribution['receive_date']);
      $last_month = strtotime("-1 month", $last);
      $cycle_day_after  = mktime(0, 0, 0, date('n', $last) + (date('j', $last) > $cycle_day), $cycle_day, date('Y', $last));
      $cycle_day_before = mktime(0, 0, 0, date('n', $last_month) + (date('j', $last_month) > $cycle_day), $cycle_day, date('Y', $last_month));
      if (abs($last-$cycle_day_before) < abs($last-$cycle_day_after)) {
        $last_cycle_date = $cycle_day_before;
      } else {
        $last_cycle_date = $cycle_day_after;
      }
      $next = strtotime("+$interval $unit", $last_cycle_date);
      if ($next < $max_date) {
        return $next;
      }


    } elseif ($recurring_mode == 'float') {
      if ($last_contribution==NULL) {
        // this relies on the last contribution
        return $this->getExpectedDate($rcontribution, $btx, 'static');
      }
      $last = strtotime($last_contribution['receive_date']);
      $next = strtotime("+$interval $unit", $last);
      if ($next < $max_date) {
        return $next;
      }


    } elseif ($recurring_mode == 'total') {
      $cycle_count = (int) ($total_amount / $rcontribution['amount']);
      $unit_count  = $cycle_count * $interval;
      $start_date  = strtotime($rcontribution['start_date']);
      $start_date  = mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));

      $due_date   = strtotime("+$unit_count $unit", $start_date);
      if ($due_date < $target_date) {
        $due_date = $target_date;
      }
      return $due_date;
    }

    return NULL;
  }

  /**
   * This is the hook for multimatches. If deactivated (default), it will simply
   * return the recurring contributions found.
   * If activated, it will also return virtual recurring contributions, that
   * are a combination of the ones found.
   **/
  function findCandidates($query, $config) {
    // add status restriction
    if (!empty($config->recurring_contribution_status)) {
      $query['contribution_status_id'] = array('IN' => $config->recurring_contribution_status);
    }

    $this->logMessage("Finding recurring contributions for: " . json_encode($query), 'debug');
    $rcur_result = civicrm_api3('ContributionRecur', 'get', $query);
    $this->logMessage("Found {$rcur_result['count']} recurring contributions.", 'debug');
    $rcontributions = array();
    foreach ($rcur_result['values'] as $key => $value) {
      $rcontributions[] = $value;
    }

    if ($config->multimatch) {
      $result = array();
      $all_tuples = array();
      for ($count=1; $count <= count($rcontributions); $count++) {
        // now create all <count>-tuples
        $count_tuples = $this->createTuples($rcontributions, 0, $count);
        $all_tuples = array_merge($all_tuples, $count_tuples);
        if (count($all_tuples) >= $config->multimatch_cutoff) {
          break;
        }
      }
      // joint the tuples into a virtual recurring contribution
      foreach ($all_tuples as $tuple) {
        $result[] = $this->createVirtualRecurringContribution($tuple);
        if (count($result) >= $config->multimatch_cutoff) break;
      }
      $this->logMessage("Constructed " . count($result) . " virtual recurring contributions.", 'debug');
      return $result;
    } else {
      return $rcontributions;
    }
  }

  /**
   * will create all possible <count>-tuples after $startindex
   */
  function createTuples(&$rcontributions, $start_index, $count) {
    $tuples = array();
    for ($i=$start_index; $i < count($rcontributions); $i++) {
      $tuple = array($rcontributions[$i]);
      if ($count > 1) { // $i < count($rcontributions) - $count
        $extensions = $this->createTuples($rcontributions, $i+1, $count-1);
        foreach ($extensions as $tuple_extension) {
          $tuples[] = array_merge($tuple, $tuple_extension);
        }
      } else {
        $tuples[] = $tuple;
      }
    }
    return $tuples;
  }

  /**
   * joins the tuple of recurring_contributions into 1 virtual one
   */
  function createVirtualRecurringContribution($tuple) {
    if (count($tuple) == 1) {
      return $tuple[0];
    } else {
      $virtual_rcur = $tuple[0];
      $virtual_rcur['virtual'] = $tuple;
      for ($i=1; $i < count($tuple); $i++) {
        $rcur2merge = $tuple[$i];
        foreach ($rcur2merge as $key => $value) {
          if ($key=='amount') {           // amounts get added
            $virtual_rcur['amount'] = $virtual_rcur['amount'] + $value;

          } elseif ($key=='id' || $key=='contact_id') { // multiple IDs become a list
            if (empty($virtual_rcur[$key])) {
              $virtual_rcur[$key] = $value;
            } else {
              $id_list = explode(',', $virtual_rcur[$key]);
              if (!in_array($value, $id_list)) {
                $id_list[] = $value;
                $virtual_rcur[$key] = implode(',', $id_list);
              }
            }

          } else {
            if ($rcur2merge[$key] != CRM_Utils_Array::value($key, $virtual_rcur)) {
              $virtual_rcur[$key] = '';
            }
          }
        }
      }
      return $virtual_rcur;
    }
  }

  /**
   * this is a meta-function designed to deal with virtual recurring contribtuions
   */
  public function getExpectedDateVirtual(&$rcontribution_virtual, &$btx, $recurring_mode, &$last_contribution = NULL) {
    $config   = $this->_plugin_config;
    $min_date = NULL;
    $max_date = NULL;
    $sum_date = 0.0;
    foreach ($rcontribution_virtual['virtual'] as $rcontribution) {
      $date = $this->getExpectedDate($rcontribution, $btx, $recurring_mode, $last_contribution);
      if ($date == NULL) return NULL;
      $sum_date += $date;
      if ($min_date == NULL) $min_date = $date;
      if ($max_date == NULL) $max_date = $date;
      if ($min_date > $date) $min_date = $date;
      if ($max_date < $date) $max_date = $date;
    }

    // now check, if the dates are too far apart,
    //  using the config settings for rating indivdual rcontributions
    $date_offset = $max_date - $min_date;
    if ( $date_offset < strtotime($config->acceptable_date_offset_from, 0)
      || $date_offset > strtotime($config->acceptable_date_offset_to, 0)) {

      // now use the same checks as for the individual rcontribution boundaries
      // to discard implausible virtual rcontributions:
      if ($date_offset < strtotime($config->date_offset_minimum, 0)) return NULL;
      if ($date_offset > strtotime($config->date_offset_maximum, 0)) return NULL;
    }

    // use the mean date as expected date for the virtual rcontribution
    $mean_date = ((double) $sum_date) / (double) count($rcontribution_virtual['virtual']);
    return $mean_date;
  }
}
