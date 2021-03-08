<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
| Author: J. Schuppe (schuppe@systopia.de)               |
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
 * This matcher will offer to create multiple new contribution if all the
 * required information is present.
 *
 * Example configuration:
 * {
 *     // For each contribution to be created, define its properties.
 *     "contributions": [
 *         {
 *             "contribution": {
 *                 "total_amount": 50,
 *                 "financial_type_id": 2,
 *                 "custom_211": "foobar"
 *             },
 *             "missing_amount_penalty": 1.0
 *         },
 *         {
 *             "contribution": {
 *                 "total_amount": 20,
 *                 "financial_type_id": 1,
 *                 "campaign_id": 123
 *             },
 *             "missing_amount_penalty": 0.1
 *         }
 *     ],
 *     // For the contribution to create for the remainder amount, define its
 *     // properties.
 *     "remainder": {
 *         "contribution": {
 *             "financial_type_id": 1
 *         },
 *         "min_amount_penalty": 0.05, // Penalty for undercutting minimum amount
 *         "min_amount": 1, // Absolute minimum amount
 *         "max_amount_penalty": 0.05, // Penalty for exceeding maximum amount
 *         "max_amount": 100 // Absolute maximum amount
 *     }
 * }
 */
class CRM_Banking_PluginImpl_Matcher_CreateMultipleContributions extends
  CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // Read configuration, set default values.
    $config = $this->_plugin_config;

    if (!isset($config->title)) {
      $config->title = "";
    }
    if (!isset($config->auto_exec)) {
      $config->auto_exec = FALSE;
    }
    if (!isset($config->required_values)) {
      $config->required_values = ["btx.financial_type_id"];
    }
    if (!isset($config->factor)) {
      $config->factor = 1.0;
    }
    if (!isset($config->threshold)) {
      $config->threshold = 0.0;
    }
    if (!isset($config->source_label)) {
      $config->source_label = E::ts('Source');
    }
    if (!isset($config->lookup_contact_by_name)) {
      $config->lookup_contact_by_name = ["hard_cap_probability" => 0.9];
    }
    if (!isset($config->contributions)) {
      $config->contributions = [];
    }
    if (!empty($config->contributions)) {
      foreach ($config->contributions as $index => &$tier) {
        // Set defaults for missing amount penalty for each tier.
        if (!isset($tier->missing_amount_penalty)) {
          // First tier gets penalty of 1 for not creating a suggestion at all.
          $tier->missing_amount_penalty = ($index == 0 ? 1.0 : 0.1);
        }
      }
    }
    if (!empty($config->remainder)) {
      if (isset($config->remainder->min_amount) && !isset($config->remainder->min_amount_penalty)) {
        $config->remainder->min_amount_penalty = 0.1;
      }
      if (isset($config->remainder->max_amount) && !isset($config->remainder->max_amount_penalty)) {
        $config->remainder->max_amount_penalty = 0.1;
      }
    }
  }


  /**
   * Generates a set of suggestions for the given bank transaction.
   *
   * @return array
   *   Match structures.
   */
  public function match(
    CRM_Banking_BAO_BankTransaction $btx,
    CRM_Banking_Matcher_Context $context
  ) {
    $config = $this->_plugin_config;
    $threshold = $this->getThreshold();
    $penalty = $this->getPenalty($btx);
    $data_parsed = $btx->getDataParsed();

    if (!$this->requiredValuesPresent($btx)) {
      return NULL;
    }

    // Lookup potential contacts.
    $contacts_found = $context->findContacts(
      $threshold,
      $data_parsed['name'],
      $config->lookup_contact_by_name
    );

    // Add suggestions for creating new contribution(s).
    foreach ($contacts_found as $contact_id => $contact_probability) {
      $remainder = $data_parsed['amount_parsed'];
      $contributions = [];
      foreach ($config->contributions as $index => $tier) {
        if ($remainder >= $tier->contribution->total_amount) {
          $contributions[] = (array) $tier->contribution;
          $remainder -= $tier->contribution->total_amount;
        }
        else {
          if (isset($tier->missing_amount_penalty)) {
            $penalty += $tier->missing_amount_penalty;
            $notes[] = E::ts(
              'The transaction amount undercuts the amount of %1 required for contribution %2 and the suggestion is thus being downgraded by %3 percent.',
              [
                1 => CRM_Utils_Money::format($tier->contribution->total_amount, $btx->currency),
                2 => $index + 1,
                3 => $tier->missing_amount_penalty * 100,
              ]
            );
          }
          break;
        }
      }

      if ($remainder > 0 && !empty($config->remainder)) {
        $contributions[] = (array) $config->remainder->contribution + [
            'total_amount' => $remainder,
          ];
        // Process min/max amount penalties.
        if (!empty($config->remainder->min_amount) && $remainder < $config->remainder->min_amount) {
          $penalty += $config->remainder->min_amount_penalty;
          $notes[] = E::ts(
            'The remainder amount undercuts the minimum amount of %1 and the suggestion is thus being downgraded by %2 percent.',
            [
              1 => CRM_Utils_Money::format(
                $config->remainder->min_amount,
                $btx->currency
              ),
              2 => $config->remainder->min_amount_penalty * 100,
            ]
          );
        }
        if (!empty($config->remainder->max_amount) && $remainder > $config->remainder->max_amount) {
          $penalty += $config->remainder->max_amount_penalty;
          $notes[] = E::ts(
            'The remainder amount exceeds the maximum amount of %1and the suggestion is thus being downgraded by %2 percent.',
            [
              1 => CRM_Utils_Money::format(
                $config->remainder->max_amount,
                $btx->currency
              ),
              2 => $config->remainder->max_amount_penalty * 100,
            ]
          );
        }
      }

      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(E::ts("Create %1 new contributions", [1 => count($contributions)]));
      $suggestion->setId("create-$contact_id");
      $suggestion->setParameter('contact_id', $contact_id);
      $suggestion->setParameter('contributions', $contributions);
      $suggestion->setParameter('notes', $notes);

      // Set probability manually, the automatic calculation provided by
      // addEvidence() might not be what we need here.
      $contact_probability -= $penalty;
      if ($contact_probability >= $threshold) {
        $suggestion->setProbability($contact_probability);
        $btx->addSuggestion($suggestion);
      }
    }

    return empty($this->_suggestions) ? NULL : $this->_suggestions;
  }

  /**
   * Handles the different actions, should probably be handled at base class
   * level ...
   *
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    // Create contribution.
    $query = $this->get_contribution_data(
      $btx,
      $suggestion,
      $suggestion->getParameter(
        'contact_id'
      )
    );
    $query['version'] = 3;
    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(
        E::ts("Couldn't create contribution.") . "<br/>" . E::ts(
          "Error was: "
        ) . $result['error_message'],
        E::ts('Error'),
        'error'
      );
      return TRUE;
    }

    $suggestion->setParameter('contribution_id', $result['id']);

    // save the account
    $this->storeAccountWithContact(
      $btx,
      $suggestion->getParameter('contact_id')
    );

    // wrap it up
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name(
      'civicrm_banking.bank_tx_status',
      'Processed'
    );
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return TRUE;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html
   * code, the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the
   * ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(
    CRM_Banking_Matcher_Suggestion $match,
    $parameters
  ) {
    // NOTHING to do...
  }

  /**
   * Generate html code to visualize the given match. The visualization may
   * also provide interactive form elements.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_match(CRM_Banking_Matcher_Suggestion $match, $btx) {
    $smarty_vars = [];

    $smarty_vars['notes'] = $match->getParameter('notes');

    $contact_id = $match->getParameter('contact_id');
    $contact = civicrm_api(
      'Contact',
      'getsingle',
      ['id' => $contact_id, 'version' => 3]
    );
    if (!empty($contact['is_error'])) {
      $smarty_vars['error'] = $contact['error_message'];
    }
    $smarty_vars['contact'] = $contact;

    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $contributions = $match->getParameter('contributions');
    foreach ($contributions as &$contribution) {
      // Set default and propagated parameters.
      $contribution = array_merge(
        $contribution,
        $this->get_contribution_data($btx, $match, $contact_id)
      );

      // look up financial type
      $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];

      // look up campaign
      if (!empty($contribution['campaign_id'])) {
        $campaign = civicrm_api(
          'Campaign',
          'getsingle',
          [
            'id' => $contribution['campaign_id'],
            'version' => 3,
          ]
        );
        if (!empty($contact['is_error'])) {
          $smarty_vars['error'] = $campaign['error_message'];
        }
        else {
          $smarty_vars['campaign'] = $campaign;
        }
      }
    }
    $smarty_vars['contributions'] = $contributions;

    // assign source
    $smarty_vars['source'] = CRM_Utils_Array::value('source', $contribution);
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch(
      'CRM/Banking/PluginImpl/Matcher/CreateMultipleContributions.suggestion.tpl'
    );
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
  function visualize_execution_info(
    CRM_Banking_Matcher_Suggestion $match,
    $btx
  ) {
    // just assign to smarty and compile HTML
    $smarty_vars = [];
    $smarty_vars['contribution_id'] = $match->getParameter('contribution_id');
    $smarty_vars['contact_id'] = $match->getParameter('contact_id');

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch(
      'CRM/Banking/PluginImpl/Matcher/CreateContribution.execution.tpl'
    );
    $smarty->popScope();
    return $html_snippet;
  }

  /**
   * compile the contribution data from the BTX and the propagated values
   */
  function get_contribution_data($btx, $match, $contact_id) {
    $contribution = [];
    $contribution['contact_id'] = $contact_id;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge(
      $contribution,
      $this->getPropagationSet(
        $btx,
        $match,
        'contribution'
      )
    );
    return $contribution;
  }

  /**
   * Will rate a contribution on whether it would match the bank payment
   *
   * @return array(contribution_id => score), where score is from [0..1]
   */
  public function rateContribution($contribution, $context) {
    $config = $this->_plugin_config;
    $parsed_data = $context->btx->getDataParsed();

    $target_amount = $context->btx->amount;
    if ($config->mode=="cancellation") {
      if ($target_amount > 0) return -1;
      $target_amount = -$target_amount;
    } else {
      if ($target_amount < 0) return -1;
    }
    $contribution_amount = $contribution['total_amount'];
    $target_date = strtotime($context->btx->value_date);
    $contribution_date = (int) strtotime($contribution['receive_date']);

    // check for date limits
    if ($config->received_date_check) {
      if ($contribution_date < strtotime($config->received_date_minimum, $target_date)) return -1;
      if ($contribution_date > strtotime($config->received_date_maximum, $target_date)) return -1;

      // calculate the date penalties
      $date_delta = abs($contribution_date - $target_date);
      $date_range = max(1, strtotime($config->received_date_maximum) - strtotime($config->received_date_minimum));
    } else {
      $date_range = 0;
    }

    // check for amount limits
    if ($config->amount_check) {
      // calculate the amount penalties
      $amount_delta = $contribution_amount - $target_amount;
      if (   ($contribution_amount < ($target_amount * $config->amount_relative_minimum))
        && ($amount_delta < $config->amount_absolute_minimum)) return -1;
      if (   ($contribution_amount > ($target_amount * $config->amount_relative_maximum))
        && ($amount_delta > $config->amount_absolute_maximum)) return -1;

      $amount_range_rel = $contribution_amount * ($config->amount_relative_maximum - $config->amount_relative_minimum);
      $amount_range_abs = $config->amount_absolute_maximum - $config->amount_absolute_minimum;
      $amount_range = max($amount_range_rel, $amount_range_abs);
    } else {
      $amount_range = 0;
    }

    // payment_instrument match?
    $payment_instrument_penalty = 0.0;
    if (    $config->payment_instrument_penalty
      &&  isset($contribution['payment_instrument_id'])
      &&  isset($parsed_data['payment_instrument']) ) {
      $contribution_payment_instrument_id = banking_helper_optionvalue_by_groupname_and_name('payment_instrument', $parsed_data['payment_instrument']);
      if ($contribution_payment_instrument_id != $contribution['payment_instrument_id']) {
        $payment_instrument_penalty = $config->payment_instrument_penalty;
      }
    }

    $penalty = 0.0;
    if ($date_range)   $penalty += $config->date_penalty * ($date_delta / $date_range);
    if ($amount_range) $penalty += $config->amount_penalty * (abs($amount_delta) / $amount_range);
    if ($context->btx->currency != $contribution['currency']) {
      $penalty += $config->currency_penalty;
    }
    $penalty += (float) $payment_instrument_penalty;

    return max(0, 1.0 - $penalty);
  }

  /**
   * Will get a the set of contributions of a given contact
   *
   * caution: will only the contributions of the last year
   *
   * @return an array with contributions
   */
  public function getPotentialContributionsForContact($contact_id, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    // check in cache
    $cache_key = "_contributions_${contact_id}_{$range_back}_{$config->received_date_check}";
    $contributions = $context->getCachedEntry($cache_key);
    if ($contributions != NULL) return $contributions;

    $contributions = array();
    if ($config->received_date_check) {
      $range_back = (int) $config->received_range_days;
      $date_restriction = " AND receive_date > (NOW() - INTERVAL {$range_back} DAY)";
    } else {
      $date_restriction = "";
    }
    $sql = "SELECT * FROM civicrm_contribution WHERE contact_id=${contact_id} AND is_test = 0 ${date_restriction};";
    $contribution = CRM_Contribute_DAO_Contribution::executeQuery($sql);
    while ($contribution->fetch()) {
      array_push($contributions, $contribution->toArray());
    }

    // cache result and return
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }

  /**
   * read the IDs of the accepted contribution status from the configuration
   *
   * @return an array with contribution status IDs
   */
  protected function getAcceptedContributionStatusIDs() {
    $accepted_status_ids = array();
    foreach ($this->_plugin_config->accepted_contribution_states as $status_name) {
      $status_id = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $status_name);
      if ($status_id) {
        array_push($accepted_status_ids, $status_id);
      }
    }
    return $accepted_status_ids;
  }

}

