<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017 SYSTOPIA                            |
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
 * This PostProcessor will connect the generated/matched contribution
 * with a membership
 */
class CRM_Banking_PluginImpl_PostProcessor_MembershipPayment extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->membership_id)) $config->membership_id = 'btx.membership_id';
    if (!isset($config->financial_type_ids)) $config->financial_type_ids = array(3);
    if (!isset($config->contribution_status_ids)) $config->contribution_status_ids = NULL;
    if (!isset($config->contribution_fields_required)) $config->contribution_fields_required = '';
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
    $membership_id = $this->getMembershipID($match, $matcher, $context);
    error_log("MEMBERSHIP_ID $membership_id");
    if (empty($membership_id)) return FALSE;

    $contributions = $this->getEligibleContributions($match, $matcher, $context);
    error_log("Contributions " . json_encode($contributions));
    if (empty($contributions)) return FALSE;

    // pass on to parent to check generic reasons
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
    // this is pretty straightforward
    if ($this->shouldExecute($match, $matcher, $context)) {
      $membership_id = $this->getMembershipID($match, $matcher, $context);
      $contributions = $this->getEligibleContributions($match, $matcher, $context);
      foreach ($contributions as $contribution) {
        civicrm_api3('MembershipPayment', 'create', array(
          'contribution_id' => $contribution['id'],
          'membership_id'   => $membership_id,
          ));
        // TODO: log: payment connected
      }
    }
  }

  /**
   * Extract the membership ID from the BTX
   */
  protected function getMembershipID($match, $matcher, $context) {
    // resolve the setting to a value
    error_log("MID " . $this->_plugin_config->membership_id);
    return (int) $this->getPropagationValue($context->btx, $match, $this->_plugin_config->membership_id);
  }

  /**
   * deliver the first of the eligible contributions
   * overwrites parent::getFirstContribution()
   */
  protected function getFirstContribution() {
    $contributions = $this->getEligibleContributions();
    if (empty($contributions)) {
      return NULL;
    } else {
      return reset($contributions);
    }
  }

  /**
   * Extract the membership ID from the BTX
   */
  protected function getEligibleContributions($match, $matcher, $context) {
    $cache_key = "{$this->_plugin_id}_contributions_{$context->btx->id}";
    error_log("CACHE KEY $cache_key");
    $cached_result = $context->getCachedEntry($cache_key);
    if ($cached_result !== NULL) return $cached_result;

    $connected_contribution_ids = $this->getContributionIDs($match, $matcher, $context);
    if (empty($connected_contribution_ids)) {
      return array();
    }

    // compile a query
    $config = $this->_plugin_config;
    $contribution_query = array(
      'id'           => array('IN' => $connected_contribution_ids),
      'option.limit' => 0,
      'sequential'   => 1);

    // add financial types
    if (!empty($config->financial_type_ids && is_array($config->financial_type_ids))) {
      $contribution_query['financial_type_ids'] = array('IN' => $config->financial_type_ids);
    }

    // add status ids
    if (!empty($config->contribution_status_ids && is_array($config->contribution_status_ids))) {
      $contribution_query['contribution_status_ids'] = array('IN' => $config->contribution_status_ids);
    }

    // add return clause
    if (!empty($config->contribution_fields_required)) {
      $contribution_query['return'] = $config->contribution_fields_required;
    }

    // query DB
    error_log("QUERY " . json_encode($contribution_query));
    $result = civicrm_api3('Contribution', 'get', $contribution_query);
    $contributions = $result['values'];

    // cache result
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }
}
