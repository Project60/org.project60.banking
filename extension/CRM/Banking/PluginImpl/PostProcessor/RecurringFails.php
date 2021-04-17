<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
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
 * This PostProcessor can take actions upon failed recurring contributions
 */
class CRM_Banking_PluginImpl_PostProcessor_RecurringFails extends CRM_Banking_PluginModel_PostProcessor {

  protected static $sepa_recurring_payment_instrument_ids = NULL;

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->cancel_reason_field))             $config->cancel_reason_field = 'cancel_reason';
    if (!isset($config->mode))                            $config->mode = 'mandate';  // also valid: contact, account, contact_account
    if (!isset($config->rules))                           $config->rules = array();
    if (!isset($config->recurring_contribution_pi_ids))   $config->recurring_contribution_pi_ids = $this->getSepaRecurringPaymentInstrumentIDs();
    if (empty($config->contribution_success_status_ids))  $config->contribution_success_status_ids = array(1);
    if (empty($config->contribution_failed_status_ids))   $config->contribution_failed_status_ids = array(3,4,7);
  }



  /**
   * @inheritDoc
   */
  protected function shouldExecute(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    $preview = FALSE
  ) {
    $config = $this->_plugin_config;

    // check if there are rules
    if (empty($config->rules) || !is_array($config->rules)) {
      $this->logMessage("NO rules configured", 'debug');
      return FALSE;
    }

    // Is any of the contributions a cancelled SEPA one?
    $cancelled_contributions = $this->getEligibleContributions($context);
    if (empty($cancelled_contributions)) {
      $this->logMessage("No SEPA cancellation.", 'debug');
      return FALSE;
    }

    // pass on to parent to check generic filters
    return parent::shouldExecute($match, $matcher, $context, $preview);
  }


  /**
   * Post-process the (already executed) match
   *
   * @param $match    CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher  CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context  CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   *
   * @throws Exception
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    if ($this->shouldExecute($match, $matcher, $context)) {
      // do this for each contribution (usually just one):
      $cancelled_contributions = $this->getEligibleContributions($context);
      foreach ($cancelled_contributions as $cancelled_contribution) {
        // get some information on the recurring information
        $mandate_stats = $this->getRecurringContributionStats($cancelled_contribution);
        if (empty($mandate_stats)) {
          $this->logMessage("No RecurringContribution for contribution [{$cancelled_contribution['id']}] found. No further actions possible.", 'warn');
          continue;
        }

        // run the rules
        foreach ($config->rules as $rule) {
          if ($this->ruleShouldExecute($rule, $cancelled_contribution, $mandate_stats)) {
            // rule matches the criteria -> execute
            $this->executeRule($rule, $cancelled_contribution, $mandate_stats, $context, $match);

            // only if instructed to do so, continue with the next rule
            if (empty($rule->continue)) {
              break;
            }
          }
        }
      }
    }
  }

  /**
   * Check if the rule matches the criteria to be executed
   *
   * @param $rule array the rule specs
   * @param $cancelled_contribution array cancelled contribution data
   * @param $mandate_stats array previously gathered stats on the recurring contribution or mandate
   * @return bool
   */
  protected function ruleShouldExecute($rule, $cancelled_contribution, $mandate_stats) {
    if (!empty($rule->mandate_validated)) {
      // check if mandate has been validated
      if (empty($mandate_stats['successful_collections'])) {
        $this->logMessage("Rule '{$rule->name}' not executed: not validated.", 'debug');
        return FALSE;
      }
    }

    if (!empty($rule->cancel_reason_matches)) {
      if (!preg_match($rule->cancel_reason_matches, $cancelled_contribution['cancel_reason'])) {
        $this->logMessage("Rule '{$rule->name}' not executed: cancel reason doesn't match.", 'debug');
        return FALSE;
      }
    }

    if (!empty($rule->successful_collections_at_least)) {
      if ($mandate_stats['successful_collections'] < $rule->successful_collections_at_least) {
        $this->logMessage("Rule '{$rule->name}' not executed: not enough successful collections.", 'debug');
        return FALSE;
      }
    }

    if (!empty($rule->sequential_successful_collections_at_least)) {
      if ($mandate_stats['sequential_successful_collections'] < $rule->sequential_successful_collections_at_least) {
        $this->logMessage("Rule '{$rule->name}' not executed: not enough sequential successful collections.", 'debug');
        return FALSE;
      }
    }

    if (isset($rule->sequential_failed_collections_at_least)) {
      if ($mandate_stats['sequential_failed_collections'] < $rule->sequential_failed_collections_at_least) {
        $this->logMessage("Rule '{$rule->name}' not executed: not enough sequential failed collections.", 'debug');
        return FALSE;
      }
    }

    if (isset($rule->sequential_failed_collections_at_most)) {
      if ($mandate_stats['sequential_failed_collections'] > $rule->sequential_failed_collections_at_most) {
        $this->logMessage("Rule '{$rule->name}' not executed: too many sequential failed collections.", 'debug');
        return FALSE;
      }
    }

    if (isset($rule->recurring_contribution_conditions) && is_array($rule->recurring_contribution_conditions)) {
      foreach ($rule->recurring_contribution_conditions as $condition) {
        $condition_met = $this->evaluateCondition($mandate_stats['contribution_recur'], $condition);
        if (!$condition_met) {
          $condition_text = json_encode($condition);
          $this->logMessage("Rule '{$rule->name}' not executed: condition [{$condition_text}] not met.", 'debug');
          return FALSE;
        }
      }
    }

    // everything seems to check out...
    return TRUE;
  }

  /**
   * Execute the given rule, the conditions have already been checked...
   *
   * @param $rule
   * @param $contribution
   * @param $mandate_stats
   * @param $context
   * @param $match
   * @throws CiviCRM_API3_Exception
   */
  protected function executeRule($rule, $contribution, $mandate_stats, $context, $match) {
    $this->logMessage("Execute rule '{$rule->name}'...", 'debug');

    if (!empty($rule->actions) && is_array($rule->actions)) {
      foreach ($rule->actions as $action) {

        if ($action->type == 'api') {
          // ===== RUN API COMMAND =====

          // compile call parameters
          $params = array();
          foreach ($action->params as $key => $value) {
            if ($value !== NULL) {
              $params[$key] = $value;
            }
          }

          foreach ($action->param_propagation as $value_source => $value_key) {
            $value = $this->getPropagationValue($context->btx, $match, $value_source);
            if ($value !== NULL) {
              $params[$value_key] = $value;
            }
          }

          // call API
          try {
            $this->logMessage("CALLING {$action->entity}.{$action->action} with " . json_encode($params), 'debug');
            civicrm_api3($action->entity, $action->action, $params);
          } catch(Exception $e) {
            $this->logMessage("CALLING {$action->entity}.{$action->action} failed: " . $e->getMessage(), 'error');
          }


        } elseif ($action->type == 'terminate') {
          // ====== TERMINATE RECURRING CONTRIBUTION / MANDATE =====
          if (!empty($mandate_stats['mandate'])) {
            // TERMINATE CiviSEPA mandate
            $cancel_reason = empty($action->cancel_reason) ? NULL : $action->cancel_reason;
            $this->logMessage("Terminating SepaMandate '{$mandate_stats['mandate']['reference']}' with reason '{$cancel_reason}'...", 'info');
            CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_stats['mandate']['id'], date('YmdHis'), $cancel_reason);

          } else {
            // TERMINATE recurring contribution (e.g. standing order)
            $new_status_id = empty($action->cancelled_status_id) ? 3 : $action->cancelled_status_id;
            $this->logMessage("Terminating recurring contribution [{$mandate_stats['contribution_recur_id']}] with status {$new_status_id}...", 'info');
            civicrm_api3('ContributionRecur', 'create', array(
                'id'                     => $mandate_stats['contribution_recur_id'],
                'contribution_status_id' => $new_status_id,
                'end_date'               => date('YmdHis')));
          }


        } else {
          // unknown type
          $this->logMessage("Rule '{$rule->name}' action has unkown type: '{$action->type}. Ignored.'", 'warn');
        }
      }
    }
  }

  /**
   * Extract from all the associated contributions the ones that are cancelled SEPA mandates
   *
   * @param $context CRM_Banking_Matcher_Context
   *
   * @return array all contributions matching the criteria
   * @throws Exception
   */
  protected function getEligibleContributions($context) {
    $config = $this->_plugin_config;

    $cache_key = "{$this->_plugin_id}_eligiblecontributions_{$context->btx->id}";
    $cached_result = $context->getCachedEntry($cache_key);
    if ($cached_result !== NULL) return $cached_result;

    $connected_contribution_ids = $this->getContributionIDs($context);
    if (empty($connected_contribution_ids)) {
      return array();
    }

    // compile a query
    $contribution_query = array(
        'id'           => array('IN' => $connected_contribution_ids),
        'option.limit' => 0,
        'return'       => 'id,contribution_recur_id,payment_instrument_id,contact_id,contribution_status_id,cancel_reason',
        'sequential'   => 1);
    if (!empty($config->recurring_contribution_pi_ids) && is_array($config->recurring_contribution_pi_ids)) {
      $contribution_query['payment_instrument_id'] = array('IN' => $config->recurring_contribution_pi_ids);
    }
    if (!empty($config->contribution_failed_status_ids) && is_array($config->contribution_failed_status_ids)) {
      $contribution_query['contribution_status_id'] = array('IN' => $config->contribution_failed_status_ids);
    }

    // query DB
    $result = civicrm_api3('Contribution', 'get', $contribution_query);
    $contributions = $result['values'];

    // cache result
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }

  /**
   * Gather some statistics on the given contribution's mandate
   *
   * @param $contribution array the contribution data we want the stats to
   * @return array statistics
   * @throws Exception
   */
  protected function getRecurringContributionStats($contribution) {
    $config = $this->_plugin_config;
    $stats = array();

    if (!empty($contribution['contribution_recur_id'])) {
      $stats['contribution_recur_id'] = (int) $contribution['contribution_recur_id'];
      $stats['contribution_recur'] = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $stats['contribution_recur_id']));

      // TODO: look up mandate
      $sepa_pis = $this->getSepaRecurringPaymentInstrumentIDs();
      if (in_array($stats['contribution_recur']['payment_instrument_id'], $sepa_pis)) {
        $mandates = civicrm_api3('SepaMandate', 'get', array(
            'entity_id'    => $stats['contribution_recur_id'],
            'entity_table' => 'civicrm_contribution_recur'));
        if ($mandates['id']) {
          $stats['mandate'] = reset($mandates['values']);
        }
      }

      // run an SQL query to get the sequence from the recurring contribution(s)
      $successful_status_ids = implode(',', $config->contribution_success_status_ids);
      $failed_status_ids     = implode(',', $config->contribution_failed_status_ids);
      $join_clauses          = array();
      $where_clauses         = array();
      $where_clauses[]       = "(c.contribution_status_id IN ({$failed_status_ids}) OR c.contribution_status_id IN ({$successful_status_ids}))";

      switch ($config->mode) {
        case 'mandate':
          $where_clauses[] = "(c.contribution_recur_id = {$stats['contribution_recur_id']})";
          break;

        case 'account':
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        m  ON m.entity_id = {$stats['contribution_recur_id']} AND m.entity_table = 'civicrm_contribution_recur'";
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        am ON am.iban = m.iban AND am.type = m.type";
          $where_clauses[] = "(c.contribution_recur_id = am.entity_id)";
          break;

        case 'contact_account':
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        m  ON m.entity_id = {$stats['contribution_recur_id']} AND m.entity_table = 'civicrm_contribution_recur'";
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        am ON am.iban = m.iban AND am.type = m.type AND am.contact_id = m.contact_id";
          $where_clauses[] = "(c.contribution_recur_id = am.entity_id)";
          break;

        case 'contact':
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        m  ON m.entity_id = {$stats['contribution_recur_id']} AND m.entity_table = 'civicrm_contribution_recur'";
          $join_clauses[] = "LEFT JOIN civicrm_sdd_mandate        am ON am.type = m.type AND am.contact_id = m.contact_id";
          $where_clauses[] = "(c.contribution_recur_id = am.entity_id)";
          break;

        default:
          // TODO: error
          throw new Exception('RecurringFails PostProcessor: undefined mode "{$config->mode}"!');
      }

      // compile query
      $join_clauses_sql   = implode("\n ", $join_clauses);
      $where_clauses_sql  = implode(" AND ", $where_clauses);
      $sequence_query_sql = "
        SELECT IF(c.contribution_status_id IN ({$successful_status_ids}), 'S', 'F') AS sequence
        FROM civicrm_contribution c
        {$join_clauses_sql}
        WHERE {$where_clauses_sql}
        GROUP BY c.id
        ORDER BY c.receive_date ASC;";
      $sequence_query = CRM_Core_DAO::executeQuery($sequence_query_sql);
      $sequence = ''; // GROUP_CONTACT doesn't respect the defined order!
      while ($sequence_query->fetch()) {
        $sequence .= $sequence_query->sequence;
      }
      $this->logMessage("Recurring sequence detected ({$config->mode}): {$sequence}", 'debug');

      // evaluate sequences
      $stats['failed_collections']     = preg_match_all("#F#", $sequence);
      $stats['successful_collections'] = preg_match_all("#S#", $sequence);
      if (preg_match("#(?P<tail>F+)$#", $sequence, $matches)) {
        $stats['sequential_failed_collections'] = strlen($matches['tail']);
      } else {
        $stats['sequential_failed_collections'] = 0;
      }
      if (preg_match("#(?P<tail>S+)$#", $sequence, $matches)) {
        $stats['sequential_successful_collections'] = strlen($matches['tail']);
      } else {
        $stats['sequential_successful_collections'] = 0;
      }

      return $stats;
    }
  }

  /**
   * Simply look up CiviSEPA's recurring payment instruments
   *
   * @return array contribution IDs
   * @throws Exception
   */
  protected function getSepaRecurringPaymentInstrumentIDs() {
    if (self::$sepa_recurring_payment_instrument_ids === NULL) {
      self::$sepa_recurring_payment_instrument_ids = array();
      $query = civicrm_api3('OptionValue', 'get', array(
          'option_group_id' => 'payment_instrument',
          'name'            => array('IN' => array('RCUR', 'FRST')),
          'return'          => 'value'));
      foreach ($query['values'] as $option_value) {
        self::$sepa_recurring_payment_instrument_ids[] = $option_value['value'];
      }
    }
    return self::$sepa_recurring_payment_instrument_ids;
  }
}

