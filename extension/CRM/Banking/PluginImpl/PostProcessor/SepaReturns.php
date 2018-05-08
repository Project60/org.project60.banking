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


/**
 * This PostProcessor call an API action if triggered
 */
class CRM_Banking_PluginImpl_PostProcessor_SepaReturns extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->return_code_field))               $config->return_code_field = 'cancel_reason';
    if (!isset($config->rules))                           $config->rules = array();
    if (!isset($config->recurring_contribution_pi_ids))   $config->recurring_contribution_pi_ids = $this->getSepaRecurringPaymentInstrumentIDs();
    if (empty($config->contribution_success_status_ids))  $config->contribution_success_status_ids = array(1);
    if (empty($config->contribution_failed_status_ids))   $config->contribution_failed_status_ids = array(3,4,7);
  }



  /**
   * Should this postprocessor spring into action?
   * Evaluates the common 'required' fields in the configuration
   *
   * @param $match    the executed match
   * @param $btx      the related transaction
   * @param $context  the matcher context contains cache data and context information
   *
   * @return bool     should the this postprocessor be activated
   */
  protected function shouldExecute(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
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
    return parent::shouldExecute($match, $matcher, $context);
  }


  /**
   * Postprocess the (already executed) match
   *
   * @param $match    the executed match
   * @param $btx      the related transaction
   * @param $context  the matcher context contains cache data and context information
   *
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    if ($this->shouldExecute($match, $matcher, $context)) {
      // do this for each contribution (usually just one):
      $cancelled_contributions = $this->getEligibleContributions($context);
      foreach ($cancelled_contributions as $cancelled_contribution) {
        $mandate_stats = $this->getMandateStats($cancelled_contribution);
        foreach ($config->rules as $rule) {
          if ($this->ruleShouldExecute($rule, $cancelled_contribution, $mandate_stats)) {
            // rule matches the criteria -> execute
            $this->executeRule($rule);

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
   * Extract from all the associated contributions the ones that are cancelled SEPA mandates
   *
   * @param $match
   * @param $matcher
   * @param $context
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
        'return'       => 'id,contribution_recur_id,payment_instrument_id,contact_id,contribution_status_id',
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
   * @param $cancelled_contribution
   */
  protected function getMandateStats($contribution) {
    $stats = array();

    if (!empty($contribution['contribution_recur_id'])) {
      $stats['contribution_recur_id'] = (int) $contribution['contribution_recur_id'];

      // TODO: look up mandate

      // run an SQL query to get the sequence from the recurring contribution
      $successful_status_ids = implode(',', $config->contribution_success_status_ids);
      $failed_status_ids = implode(',', $config->$failed_status_ids);
      $sequence = CRM_Core_DAO::singleValueQuery("
        SELECT GROUP_CONCAT(IF(c.contribution_status_id IN ({$successful_status_ids}), 'S', 'F')) AS sequence
        FROM civicrm_contribution c
        WHERE c.contribution_recur_id = {$stats['contribution_recur_id']}
          AND (c.contribution_status_id IN ({$failed_status_ids}) OR c.contribution_status_id IN ({$successful_status_ids})) 
        ORDER BY c.receive_date
        GROUP BY c.contribution_recur_id
        ");
    }
  }
  
}

