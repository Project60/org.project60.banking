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
class CRM_Banking_PluginImpl_Matcher_Membership extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))           $config->threshold = 0.5;
    if (!isset($config->general_options))     $config->general_options = array();
    if (!isset($config->membership_options))  $config->membership_options = array();

    // if TRUE, the start_date will be used to determine the payment cycle,
    //   if FALSE, the join_date will be used.
    if (!isset($config->based_on_start_date)) $config->based_on_start_date = TRUE;
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $data_parsed = $btx->getDataParsed();

    // find potential contacts
    $contacts_found = $context->findContacts($threshold, $data_parsed['name'], $config->lookup_contact_by_name);

    // with the identified contacts, look up matching memberships
    $memberships = $this->findMemberships($contacts_found, $btx, $context);

    // transform all memberships into suggestions
    foreach ($memberships as $membership) {
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      if (isset($config->general_options->suggestion_title)) {
        $suggestion->setTitle($config->general_options->suggestion_title);
      } else {
        $suggestion->setTitle(E::ts("Record as Membership Fee"));
      }

      $suggestion->setId("membership-".$membership['id']);
      $suggestion->setParameter('membership_id', $membership['id']);
      $suggestion->setParameter('last_fee_id',   $membership['last_fee_id']);
      $suggestion->setProbability($membership['probability']);
      $btx->addSuggestion($suggestion);
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
    $membership_id = $suggestion->getParameter('membership_id');
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));
    $membership_type = civicrm_api3('MembershipType', 'getsingle', array('id' => $membership['membership_type_id']));


    // TODO: verify validity of suggestion (is outdated?)

    // 1. create contribution
    $contribution_parameters = array(
        'contact_id'        => $membership['contact_id'],
        'total_amount'      => $btx->amount,
        'currency'          => $btx->currency,
        'receive_date'      => $btx->value_date,
        'financial_type_id' => $this->getMembershipOption($membership_type['id'], 'financial_type_id', $membership_type['financial_type_id']),
        'version'           => 3,
      );
    $contribution_parameters = array_merge($contribution_parameters, $this->getPropagationSet($btx, $suggestion, 'contribution'));
    $contribution = civicrm_api('Contribution', 'create',  $contribution_parameters);
    if (!empty($contribution['is_error'])) {
      CRM_Core_Session::setStatus(E::ts("Couldn't create contribution.")."<br/>".E::ts("Error was: ").$contribution['error_message'], E::ts('Error'), 'error');
      return true;
    }

    // 2. connect to membership
    civicrm_api3('MembershipPayment', 'create',  array(
      'membership_id'   => $membership_id,
      'contribution_id' => $contribution['id']
      ));

    // wrap it up
    $suggestion->setParameter('contact_id',      $membership['contact_id']);
    $suggestion->setParameter('contribution_id', $contribution['id']);
    $this->storeAccountWithContact($btx, $membership['contact_id']);
    CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution['id']);

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
    $config = $this->_plugin_config;
    $smarty_vars = array();

    // load the contribution
    $membership_id = $match->getParameter('membership_id');
    $last_fee_id   = $match->getParameter('last_fee_id');

    // LOAD entities
    // TODO: error handling
    $membership        = civicrm_api('Membership', 'getsingle', array('id' => $membership_id, 'version'=>3));
    $membership_type   = civicrm_api('MembershipType', 'getsingle', array('id' => $membership['membership_type_id'], 'version'=>3));
    $membership_status = civicrm_api('MembershipStatus', 'getsingle', array('id' => $membership['status_id'], 'version'=>3));
    $contact           = civicrm_api('Contact', 'getsingle', array('id' => $membership['contact_id'], 'version'=>3));

    // load last fee
    if (!empty($last_fee_id)) {
      $last_fee                = civicrm_api('Contribution', 'getsingle', array('id' => $last_fee_id, 'version'=>3));
      $last_fee['days']        = round((strtotime($btx->booking_date)-(int) strtotime($last_fee['receive_date'])) / (60 * 60 * 24));
      $smarty_vars['last_fee'] = $last_fee;
    }

    // calculate some stuff
    $date_field = ($config->based_on_start_date)?'start_date':'join_date';
    $membership['days'] = round((strtotime($btx->booking_date)-strtotime($membership[$date_field])) / (60 * 60 * 24));
    $membership['percentage_of_minimum'] = round(($btx->amount / (float) $membership_type['minimum_fee']) * 100);
    $membership['title'] = $this->getMembershipOption($membership['membership_type_id'], 'title', $membership_type['name']);

    // assign to smarty and compile HTML
    $smarty_vars['membership']        = $membership;
    $smarty_vars['membership_type']   = $membership_type;
    $smarty_vars['membership_status'] = $membership_status;
    $smarty_vars['contact']           = $contact;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/Membership.suggestion.tpl');
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
    // just assign to smarty and compile HTML
    $smarty_vars = array();
    $smarty_vars['membership_id']    = $match->getParameter('membership_id');
    $smarty_vars['contribution_id']  = $match->getParameter('contribution_id');
    $smarty_vars['contact_id']       = $match->getParameter('contact_id');

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/Membership.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }


  /**
   * This function will use the given parameters to find
   * all potential membership IDs with the contacts found.
   */
  protected function findMemberships($contact2probability, $btx, $context) {
    if (empty($contact2probability)) return array();

    $config = $this->_plugin_config;
    $penalty     = $this->getPenalty($btx);
    $memberships = array();
    $query_sql = $this->createSQLQuery(array_keys($contact2probability), $btx->amount, $context);
    $query = CRM_Core_DAO::executeQuery($query_sql);
    while ($query->fetch()) {
      $memberships[] = array(
        'id'                           => $query->id,
        'contact_id'                   => $query->contact_id,
        'membership_type_id'           => $query->membership_type_id,
        'expected_fee'                 => $query->expected_fee,
        'last_fee_id'                  => $query->last_fee_id,
        'last_fee_amount'              => $query->last_fee_amount,
        'last_fee_date'                => $query->last_fee_date,
        'membership_start_date'        => $query->membership_start_date,
        'membership_duration_unit'     => $query->membership_duration_unit,
        'membership_duration_interval' => $query->membership_duration_interval,
        'membership_period_type'       => $query->membership_period_type,
        'membership_minimum_fee'       => $query->membership_minimum_fee,
        );
    }

    // now rate all the memberships, and cut off the ones under the threshold
    $result = array();
    foreach ($memberships as $membership) {
      $probability = $this->rateMembership($membership, $btx, $context);
      if (isset($contact2probability[$membership['contact_id']])) {
        $probability *= $contact2probability[$membership['contact_id']];
      }

      $probability -= $penalty;
      if ($probability >= $config->threshold) {
        $membership['probability'] = $probability;
        $result[] = $membership;
      }
    }

    return $result;
  }


  /**
   * This function will generate an SQL statement to
   * find all relevant memberships. It should also
   * provide all values necessary to rate the membership
   * for probability
   *
   * the query itself is derived from the plugin's configuration
   * and will be cached.
   *
   * @return SQL string
   */
  protected function createSQLQuery($contact_ids, $amount, $context) {
    $cache_key = "matcher_membership_" . $this->_plugin_id . "_query";
    $query = CRM_Utils_StaticCache::getCachedEntry($cache_key);
    if ($query == NULL) {
      // NOT CACHED, build query
      $config = $this->_plugin_config;
      $date_field = ($config->based_on_start_date)?'start_date':'join_date';
      $base_query = "
      SELECT
        civicrm_membership.id                     AS id,
        civicrm_membership.contact_id             AS contact_id,
        civicrm_membership.membership_type_id     AS membership_type_id,
        civicrm_membership.$date_field            AS membership_start_date,
        civicrm_membership_type.duration_unit     AS membership_duration_unit,
        civicrm_membership_type.duration_interval AS membership_duration_interval,
        civicrm_membership_type.period_type       AS membership_period_type,
        civicrm_membership_type.minimum_fee       AS membership_minimum_fee,
        MAX(civicrm_contribution.id)              AS last_fee_id,
        AVG(civicrm_contribution.total_amount)    AS last_fee_amount,
        civicrm_contribution.receive_date         AS last_fee_date
      FROM
        civicrm_membership
      LEFT JOIN
        civicrm_membership_type    ON civicrm_membership.membership_type_id = civicrm_membership_type.id
      LEFT JOIN
        civicrm_contribution       ON civicrm_contribution.id = (%s)
      WHERE
        civicrm_membership.contact_id           IN (CONTACT_IDS)
      AND civicrm_membership.membership_type_id IN (%s)
      AND (%s)
      GROUP BY
        civicrm_membership.id,
        civicrm_contribution.receive_date;
      ";

      // LATEST CONTRIBUTION CRITERIA:
      $contribution_subquery = "
      SELECT    last_contribution.id
      FROM      civicrm_contribution AS last_contribution
      LEFT JOIN civicrm_membership_payment
             ON civicrm_membership_payment.contribution_id = last_contribution.id
      WHERE     last_contribution.contribution_status_id = 1
      AND       last_contribution.is_test = 0
      AND       civicrm_membership_payment.membership_id = civicrm_membership.id
      ORDER BY  receive_date DESC
      LIMIT 1";

      // load all membership types
      $membership_types = array();
      $_membership_types = civicrm_api3('MembershipType', 'get', array('option.limit' => 99999));
      foreach ($_membership_types['values'] as $membership_type) {
        $membership_types[$membership_type['id']] = $membership_type;
      }

      // get $membership_type_id_list
      $membership_type_ids = array();
      if (isset($config->general_options->membership_type_ids)) {
        // if there is a given list, we'll take it
        $membership_type_ids_option = explode(',', $config->general_options->membership_type_ids);
        foreach ($membership_type_ids_option as $membership_type_id) {
          if ((int) $membership_type_id) {
            $membership_type_ids[] = (int) $membership_type_id;
          }
        }
      } else {
        // if there is no given list, we'll take all active type
        foreach ($membership_types as $membership_type_id => $membership_type) {
          if ($membership_type['is_active']) {
            $membership_type_ids[] = (int) $membership_type_id;
          }
        }
      }
      //if (empty($membership_type_ids)) throw Exception("matcher_membership: No active membership types found.");

      // compile $membership_type_clauses
      $membership_type_clauses = array();
      foreach ($membership_type_ids as $membership_type_id) {
        $amount_range = $this->getMembershipAmountRange($membership_types[$membership_type_id], $context);
        $membership_type_clauses[] = "(
          (civicrm_membership.membership_type_id = $membership_type_id)
          AND
          (BTX_AMOUNT >= ${amount_range[0]})
          AND
          (BTX_AMOUNT <= ${amount_range[1]})
          )";
      }

      // compile final query:
      $membership_type_id_list     = implode(',',    $membership_type_ids);
      $membership_type_clauses_sql = implode(' OR ', $membership_type_clauses);
      $query = sprintf($base_query, $contribution_subquery,
                                    $membership_type_id_list,
                                    $membership_type_clauses_sql);

      // normalize query (remove extra whitespaces)
      $query = preg_replace('/\s+/', ' ', $query);

      // and cache the result
      CRM_Utils_StaticCache::setCachedEntry($cache_key, $query);
    }

    // insert the contact IDs
    $contact_id_list = implode(',', $contact_ids);
    $final_sql = str_replace('CONTACT_IDS', $contact_id_list, $query);
    $final_sql = str_replace('BTX_AMOUNT',  $amount,          $final_sql);
    //error_log($final_sql);
    return $final_sql;
  }

  /**
   * This function will find out the amount range that would match the given type
   *
   * @return array($min_amount, $max_amount, $exact_amount)
   */
  protected function getMembershipAmountRange($membership_type, $context) {
    $config = $this->_plugin_config;
    $expected_fee = (float) $this->getMembershipOption($membership_type['id'],
                                'membership_fee', $membership_type['minimum_fee']);
    $min_factor   = (float) $this->getMembershipOption($membership_type['id'],
                                'amount_min',     1.0);
    $max_factor   = (float) $this->getMembershipOption($membership_type['id'],
                                'amount_max',     1.0);
    return array($expected_fee * $min_factor, $expected_fee * $max_factor, $expected_fee);
  }

  /**
   * Helper function to get an option for a certain membership type id
   * These options can be specified in the general_options,
   * but overwritten int the membership_options.
   */
  protected function getMembershipOption($membership_type_id, $option_name, $default) {
    $config = $this->_plugin_config;
    $value = NULL;
    if (isset($config->general_options->$option_name)) {
      // get the value from the general_options
      $value = $config->general_options->$option_name;
    }
    if (isset($config->membership_options->$membership_type_id->$option_name)) {
      // overwrite if there's a specific option set for this type
      $value = $config->membership_options->$membership_type_id->$option_name;
    }
    if ($value === NULL) {
      return $default;
    } else {
      return $value;
    }
  }

  /**
   * This function will evaluate the given membership instance data
   * wrt probability.
   *
   * @return float [0..1]
   */
  protected function rateMembership(&$membership, $btx, $context) {
    $rating = 1.0;

    $amount_penalty = $this->getMembershipOption($membership['membership_type_id'], 'amount_penalty', 0.0);
    if ($amount_penalty) {
      // expected fee is the last paid amount, or the minimum fee
      if ($membership['last_fee_amount']) {
        $expected_fee = $this->getMembershipOption($membership['membership_type_id'], 'expected_fee', $membership['last_fee_amount']);
      } else {
        $expected_fee = $this->getMembershipOption($membership['membership_type_id'], 'expected_fee', $membership['membership_minimum_fee']);
      }

      $amount_deviation_relative_min = $this->getMembershipOption($membership['membership_type_id'], 'amount_deviation_relative_min', 1.0);
      $amount_deviation_relative_max = $this->getMembershipOption($membership['membership_type_id'], 'amount_deviation_relative_max', 1.0);
      $relative_deviation = ((float)$btx->amount / (float) $expected_fee);
      if ($relative_deviation < $amount_deviation_relative_min || $relative_deviation > $amount_deviation_relative_max) {
        $rating -= $amount_penalty;
      }
    }

    $date_penalty = $this->getMembershipOption($membership['membership_type_id'], 'date_penalty', 0.0);
    if ($date_penalty) {
      $date_deviation_relative_min = $this->getMembershipOption($membership['membership_type_id'], 'date_deviation_relative_min', 0.8);
      $date_deviation_relative_max = $this->getMembershipOption($membership['membership_type_id'], 'date_deviation_relative_max', 1.2);

      // TODO: get estimated next date (since last payment OR start_date)
      if ($membership['last_fee_date']) {
        $reference_date = strtotime($membership['last_fee_date']);
      } else {
        $reference_date = strtotime($membership['membership_start_date']);
      }
      $period_type     = $this->getMembershipOption($membership['membership_type_id'], 'period_type',     $membership['membership_period_type']);
      $period_unit     = $this->getMembershipOption($membership['membership_type_id'], 'period_unit',     $membership['membership_duration_unit']);
      $period_interval = $this->getMembershipOption($membership['membership_type_id'], 'period_interval', $membership['membership_duration_interval']);
      if ($period_type == 'rolling') {
        // ROLLING: the expected date is one interval after the last payment (or start date)
        $expected_fee_date = strtotime("+$period_interval $period_unit", $reference_date);
      } else if ($period_type == 'fixed') {
        // FIXED: the expected date is one interval after the last payment (or start date)
        // TODO: Implement evaluating fixed_period dates...until then: use same as ROLLING
        $expected_fee_date = strtotime("+$period_interval $period_unit", $reference_date);
      } else {
        // OTHER: no expected date, set to the booking date of the payment
        $expected_fee_date = strtotime($btx->booking_date);
      }

      $date_deviation = strtotime($btx->booking_date) - $expected_fee_date;
      $period_length  = strtotime("+$period_interval $period_unit") - strtotime("now");
      $date_deviation_relative = $date_deviation / $period_length;

      if ($date_deviation_relative < $date_deviation_relative_min || $date_deviation_relative > $date_deviation_relative_max) {
        $rating -= $date_penalty;
      }
    }

    return $rating;
  }
}

