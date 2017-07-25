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
class CRM_Banking_PluginImpl_PostProcessor_API extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->contribution_fields_checked)) $config->contribution_fields_checked = 'id,financial_type_id,total_amount';
    if (!isset($config->membership_id))               $config->membership_id = 'btx.membership_id';
    if (!isset($config->financial_type_ids))          $config->financial_type_ids = array(2);
    if (!isset($config->contribution_status_ids))     $config->contribution_status_ids = array(1);
    // if (!isset($config->received_date_minimum)) $config->received_date_minimum = "-10 days";


     "membership_id": "btx.membership_id",
   "financial_type_ids": [2],
   "contribution_status_ids": [1]


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
      // TODO: get membership ID
      $membership_id = 1;

      $contribution_ids = $this->getContributionIDs($match, $matcher, $context);
      if (!empty($contribution_ids)) {
        $contributions = civicrm_api3('Contribution', 'get', array(
          'id'     => array('IN' => $contribution_ids),
          'return' =>$config->contribution_fields_checked,
          ));
        foreach ($contributions['values'] as $contribution) {
          if ($this->isContributionEligibleForMembership($contribution)) {
            civicrm_api3('MembershipPayment', 'create', array(
              'contribution_id' => $contribution['id'],
              'membership_id'   => $membership_id,
              ));
          }
        }
      }
    }
  }



  protected function isContributionEligibleForMembership($contribution) {
    // TODO:
    return TRUE;
  }
}

