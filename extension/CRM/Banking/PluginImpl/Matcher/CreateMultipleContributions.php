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

/**
 * This matcher creates suggestions for creating multiple new contribution if
 * all the required information is present and the entire transaction amount can
 * be captured by contributions.
 *
 * Example configuration:
 * {
 *     // Default parameters can be defined for all contributions.
 *     "defaults": {
 *         "source": "Bank transaction processed by CiviBanking"
 *     },
 *     // For each contribution to be created, define its properties.
 *     "contributions": [
 *         {
 *             "contribution": {
 *                 "total_amount": 50,
 *                 "financial_type_id": 2,
 *                 "custom_123": "foobar"
 *             },
 *             "missing_amount_penalty": 1.0,
 *             "missing_parameters_penalty": 0.1,
 *         },
 *         {
 *             "contribution": {
 *                 "total_amount": 20,
 *                 "financial_type_id": 1,
 *                 "campaign_id": 123
 *             },
 *             "missing_amount_penalty": 0.1,
 *             "missing_parameters_penalty": 0.1,
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
class CRM_Banking_PluginImpl_Matcher_CreateMultipleContributions extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   *
   * @param $config_name
   */
  public function __construct($config_name) {
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
      $config->required_values = [];
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
    if (!isset($config->defaults)) {
      $config->defaults = [];
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
        if (!isset($tier->missing_parameters_penalty)) {
          // First tier gets penalty of 1 for not creating a suggestion at all.
          $tier->missing_parameters_penalty = ($index == 0 ? 1.0 : 0.1);
        }
      }
    }
    if (!empty($config->remainder)) {
      if (
        isset($config->remainder->min_amount)
        && !isset($config->remainder->min_amount_penalty)
      ) {
        $config->remainder->min_amount_penalty = 0.1;
      }
      if (
        isset($config->remainder->max_amount)
        && !isset($config->remainder->max_amount_penalty)
      ) {
        $config->remainder->max_amount_penalty = 0.1;
      }
    }
  }


  /**
   * Generates a set of suggestions for the given bank transaction.
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param CRM_Banking_Matcher_Context $context
   *
   * @return array
   *   Match structures.
   *
   * @throws CRM_Core_Exception
   */
  public function match(
    CRM_Banking_BAO_BankTransaction $btx,
    CRM_Banking_Matcher_Context $context
  ) {
    if ($this->requiredValuesPresent($btx)) {
      $config = $this->_plugin_config;
      $threshold = $this->getThreshold();
      $penalty = $this->getPenalty($btx);
      $data_parsed = $btx->getDataParsed();
      $notes = [];

      // Lookup potential contacts.
      $contacts_found = $context->findContacts(
        $threshold,
        $data_parsed['name'],
        $config->lookup_contact_by_name
      );

      // Add suggestions for creating new contribution(s).
      foreach ($contacts_found as $contact_id => $contact_probability) {
        $remainder = $btx->amount;
        $contributions = [];
        $skipped_contributions = [];
        $index = 0;
        foreach ($config->contributions as $index => &$tier) {
          $contribution = array_merge(
            (array) $config->defaults,
            $this->get_contribution_data($btx, $contact_id),
            (array) $tier->contribution
          );
          if ($remainder >= $contribution['total_amount']) {
            if (self::validate_contribution_data($contribution)) {
              $contributions[$index] = $contribution;
              $remainder -= $contribution['total_amount'];
            }
            else {
              $skipped_contributions[$index] = TRUE;
              $penalty += $tier->missing_parameters_penalty;
              $notes[$index][] = E::ts(
                'There are missing parameters for contribution %1 and the suggestion is thus being downgraded by %2 percent.',
                [
                  1 => $index + 1,
                  2 => $tier->missing_parameters_penalty * 100,
                ]
              );
              $this->logMessage(
                'Missing parameters for contribution ' . ($index + 1)
                . ', applying penalty of ' . $tier->missing_parameters_penalty,
                'warn'
              );
            }
          }
          else {
            $skipped_contributions[$index] = TRUE;
            if (isset($tier->missing_amount_penalty)) {
              $penalty += $tier->missing_amount_penalty;
              $notes[$index][] = E::ts(
                'The transaction amount undercuts the amount of %1 required for contribution %2 and the suggestion is thus being downgraded by %3 percent.',
                [
                  1 => CRM_Utils_Money::format(
                    $tier->contribution->total_amount,
                    $btx->currency
                  ),
                  2 => $index + 1,
                  3 => $tier->missing_amount_penalty * 100,
                ]
              );
              $this->logMessage(
                'Amount undercuts required amount of '
                . $tier->contribution->total_amount
                . ' for contribution ' . ($index + 1)
                . ', applying penalty of ' . $tier->missing_amount_penalty,
                'warn'
              );
            }
            continue;
          }
        }

        if (!empty($config->remainder)) {
          // Increase contribution count.
          $index++;

          // Process min/max amount penalties.
          if (
            !empty($config->remainder->min_amount)
            && $remainder < $config->remainder->min_amount
          ) {
            $penalty += $config->remainder->min_amount_penalty;
            $notes[$index][] = E::ts(
              'The remainder amount undercuts the minimum amount of %1 and the suggestion is thus being downgraded by %2 percent.',
              [
                1 => CRM_Utils_Money::format(
                  $config->remainder->min_amount,
                  $btx->currency
                ),
                2 => $config->remainder->min_amount_penalty * 100,
              ]
            );
            $this->logMessage(
              'Remainder amount undercuts minimum amount of '
              . $config->remainder->min_amount . ' for remainder contribution'
              . ', applying penalty of ' . $config->remainder->min_amount_penalty,
              'warn'
            );
          }
          if (
            !empty($config->remainder->max_amount)
            && $remainder > $config->remainder->max_amount
          ) {
            $penalty += $config->remainder->max_amount_penalty;
            $notes[$index][] = E::ts(
              'The remainder amount exceeds the maximum amount of %1 and the suggestion is thus being downgraded by %2 percent.',
              [
                1 => CRM_Utils_Money::format(
                  $config->remainder->max_amount,
                  $btx->currency
                ),
                2 => $config->remainder->max_amount_penalty * 100,
              ]
            );
            $this->logMessage(
              'Remainder amount exceeds maximum amount of '
              . $config->remainder->max_amount . ' for remainder contribution'
              . ', applying penalty of ' . $config->remainder->max_amount_penalty,
              'warn'
            );
          }

          if ($remainder > 0) {
            $contribution = array_merge(
              (array) $config->defaults,
              $this->get_contribution_data($btx, $contact_id),
              (array) $config->remainder->contribution + [
                'total_amount' => $remainder,
              ]
            );

            if (self::validate_contribution_data($contribution)) {
              $contributions[$index] = $contribution;
            }
            else {
              // Not enough information on how to enter the remainder amount.
              $this->logMessage(
                'Validation failed for remainder contribution',
                'warn'
              );
              continue;
            }
          }
        }
        elseif ($remainder > 0) {
          // No information on how to enter the remainder amount.
          $this->logMessage(
            'Configuration missing for remainder contribution',
            'warn'
          );
          continue;
        }

        $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
        $suggestion->setTitle(E::ts(
          "Create %1 new contributions",
          [1 => count($contributions)]
        ));
        $suggestion->setId("create-$contact_id");
        $suggestion->setParameter('contact_id', $contact_id);
        $suggestion->setParameter('contributions', $contributions);
        $suggestion->setParameter('skipped_contributions', $skipped_contributions);
        $suggestion->setParameter('notes', $notes);

        // Set probability manually, the automatic calculation provided by
        // addEvidence() might not be what we need here.
        $contact_probability -= $penalty;
        if ($contact_probability >= $threshold) {
          $suggestion->setProbability($contact_probability);
          $btx->addSuggestion($suggestion);
        }
      }
    }

    return empty($this->_suggestions) ? NULL : $this->_suggestions;
  }

  /**
   * Handles the different actions, should probably be handled at base class
   * level ...
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   * @param CRM_Banking_BAO_BankTransaction $btx
   *
   * @return bool
   *   TRUE when execution finished successfully.
   */
  public function execute($suggestion, $btx) {
    $transaction = new CRM_Core_Transaction();
    try {
      // Create contributions.
      $contribution_ids = [];
      foreach ($suggestion->getParameter('contributions') as $contribution_data) {
        $result = civicrm_api3(
          'Contribution',
          'create',
          $contribution_data
        );
        if (!empty($result['is_error'])) {
          throw new Exception(
            E::ts(
              'Contribution could not be created. Error message: %1',
              [1 => $result['error_message']]
            )
          );
        }
        else {
          $contribution_ids[] = $result['id'];
        }
      }
      $suggestion->setParameter('contribution_ids', $contribution_ids);

      // Save the account.
      $this->storeAccountWithContact(
        $btx,
        $suggestion->getParameter('contact_id')
      );

      // Wrap it up.
      $newStatus = banking_helper_optionvalueid_by_groupname_and_name(
        'civicrm_banking.bank_tx_status',
        'Processed'
      );
      foreach ($contribution_ids as $contribution_id) {
        CRM_Banking_BAO_BankTransactionContribution::linkContribution($btx->id, $contribution_id);
      }

      $btx->setStatus($newStatus);
      $result = parent::execute($suggestion, $btx);
    }
    catch (Exception $exception) {
      CRM_Core_Session::setStatus(
        $exception->getMessage(),
        E::ts('Error'),
        'error'
      );
      $transaction->rollback();
      $result = FALSE;
    }
    $transaction->commit();
    return $result;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html
   * code, the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the
   * ones that
   *  'belong' to your suggestion.
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   * @param $parameters
   */
  public function update_parameters(
    CRM_Banking_Matcher_Suggestion $match,
    $parameters
  ) {
    // NOTHING to do...
  }

  /**
   * Generate HTML code to visualize the given match. The visualization may
   * also provide interactive form elements.
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   *   Match data as previously generated by this plugin instance.
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   The bank transaction the match refers to.
   *
   * @return string
   *   HTML markup.
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

    $financial_types = CRM_Contribute_BAO_Contribution::buildOptions(
      'financial_type_id'
    );
    $contributions = $match->getParameter('contributions');
    foreach ($contributions as &$contribution) {
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
          $contribution['campaign'] = $campaign;
        }
      }
    }
    $skipped_contributions = $match->getParameter('skipped_contributions');
    $contributions = $contributions + $skipped_contributions;
    ksort($contributions);
    $smarty_vars['contributions'] = $contributions;
    $smarty_vars['source_label'] = $this->_plugin_config->source_label;

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_markup = $smarty->fetch(
      'CRM/Banking/PluginImpl/Matcher/CreateMultipleContributions.suggestion.tpl'
    );
    $smarty->popScope();
    return $html_markup;
  }

  /**
   * Generate html code to visualize the executed match.
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   *  Match data as previously generated by this plugin instance.
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   The bank transaction the match refers to.
   *
   * @return string
   *   HTML markup.
   */
  function visualize_execution_info(CRM_Banking_Matcher_Suggestion $match, $btx) {
    // just assign to smarty and compile HTML
    $smarty_vars = [];
    $smarty_vars['contribution_ids'] = $match->getParameter('contribution_ids');
    $smarty_vars['contact_id'] = $match->getParameter('contact_id');

    // assign to smarty and compile HTML
    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch(
      'CRM/Banking/PluginImpl/Matcher/CreateMultipleContributions.execution.tpl'
    );
    $smarty->popScope();
    return $html_snippet;
  }

  /**
   * Compiles the contribution data from the bank transaction and the propagated
   * values.
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param int $contact_id
   *
   * @return array
   */
  function get_contribution_data(
    CRM_Banking_BAO_BankTransaction $btx,
    $contact_id
  ) {
    $contribution = [];
    $contribution['contact_id'] = $contact_id;
    $contribution['receive_date'] = $btx->value_date;
    $contribution['currency'] = $btx->currency;
    $contribution = array_merge(
      $contribution,
      $this->getPropagationSet(
        $btx,
        NULL,
        'contribution'
      )
    );
    return $contribution;
  }

  /**
   * Validates contribution data.
   *
   * @param array $contribution
   *   An array of parameters to use for creating a contribution.
   *
   * @return bool
   *   Whether the contribution data is valid.
   */
  public static function validate_contribution_data($contribution) {
    $mandatory = [
      'contact_id',
      'financial_type_id',
    ];
    return
      (
        !empty($contribution['id'])
        || empty(array_diff_key(array_flip($mandatory), $contribution))
      )
      && $contribution['total_amount'] > 0;
  }

}
