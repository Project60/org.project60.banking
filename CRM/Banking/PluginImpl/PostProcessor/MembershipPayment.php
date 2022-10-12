<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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
    if (!isset($config->membership_id))                  $config->membership_id                   = 'btx.membership_id';
    if (!isset($config->contribution_recur_id))          $config->contribution_recur_id           = NULL;
    if (!isset($config->membership_rcur_field))          $config->membership_rcur_field           = NULL;

    if (!isset($config->set_membership_payment))         $config->set_membership_payment          = 'fill'; // options: 'no', 'fill', 'yes'(=overwrite)
    if (!isset($config->set_contribution_recur))         $config->set_contribution_recur          = 'fill'; // options: 'no', 'fill', 'yes'(=overwrite)
    if (!isset($config->set_membership_rcur_field))      $config->set_membership_rcur_field       = 'no';   // options: 'no', 'fill', 'yes'(=overwrite)

    if (!isset($config->financial_type_ids))             $config->financial_type_ids              = array(2); // Membership Dues
    if (!isset($config->payment_instrument_ids))         $config->payment_instrument_ids          = NULL;
    if (!isset($config->payment_instrument_ids_exclude)) $config->payment_instrument_ids_exclude  = NULL;
    if (!isset($config->contribution_status_ids))        $config->contribution_status_ids         = NULL;
    if (!isset($config->contribution_fields_required))   $config->contribution_fields_required    = array('id', 'contribution_recur_id');
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
    if (!$preview) {
      $contributions = $this->getEligibleContributions($context);
      if (empty($contributions)) {
        $this->logMessage("No eligible contributions found.", "debug");
        return FALSE;
      }
    }

    // pass on to parent to check generic reasons
    return parent::shouldExecute($match, $matcher, $context, $preview);
  }

  /**
   * Postprocess the (already executed) match
   *
   * @param $match    CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher  CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context  CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   *
   * @throws Exception if anything goes wrong
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    // this is pretty straightforward
    if ($this->shouldExecute($match, $matcher, $context)) {
      $contributions = $this->getEligibleContributions($context);
      foreach ($contributions as $contribution) {
        $membership_id = $this->getMembershipID($contribution, $match, $matcher, $context);
        if (!$membership_id) {
          $this->logMessage("Contribution [{$contribution['id']}] is not related to a membership", 'debug');
          continue;
        }

        // get contribution ID
        $contribution_recur_id = $this->getContributionRecurID($contribution, $membership_id, $match, $matcher, $context);
        if ($contribution_recur_id) {
          $this->logMessage("Contribution [{$contribution['id']}] should be connected to recurring contribution [{$contribution_recur_id}]", 'debug');
        } else {
          $this->logMessage("Contribution [{$contribution['id']}] should not get recurring contribution", 'debug');
        }

        // update MembershipPayment:
        if ($config->set_membership_payment == 'fill' || $config->set_membership_payment == 'yes') {
          if ($config->set_membership_payment == 'yes') {
            // overwrite means: remove other existing items
            CRM_Core_DAO::executeQuery("DELETE FROM civicrm_membership_payment 
                                                    WHERE contribution_id = {$contribution['id']} 
                                                      AND membership_id <> {$membership_id};");
          }

          // assign to membership
          civicrm_api3('MembershipPayment', 'create', array(
              'contribution_id' => $contribution['id'],
              'membership_id'   => $membership_id,
          ));
          $this->logMessage("Contribution [{$contribution['id']}] connected to membership [{$membership_id}].", 'debug');
        }

        // update contribution <-> contribution_recur connection
        if ($config->set_contribution_recur == 'yes') {
          // definitely write the given status
          if ($contribution_recur_id != $contribution['contribution_recur_id']) {
            civicrm_api3('Contribution', 'create', array(
               'id'                    => $contribution['id'],
               'contribution_recur_id' => $contribution_recur_id ? $contribution_recur_id : ''));
            $this->logMessage("Contribution [{$contribution['id']}] connected to recurring contribution [{$contribution_recur_id}].", 'debug');
          }
        } else {
          // only write if
          if ($config->set_contribution_recur == 'fill'
               && empty($contribution['contribution_recur_id'])
               && !empty($contribution_recur_id)) {
            civicrm_api3('Contribution', 'create', array(
                'id'                    =>  $contribution['id'],
                'contribution_recur_id' =>  $contribution_recur_id));
            $this->logMessage("Contribution [{$contribution['id']}] connected to recurring contribution [{$contribution_recur_id}].", 'debug');
          }
        }

        // update membership's contribution_recur field
        if ($contribution_recur_id && $config->membership_rcur_field) {
          if ($config->set_contribution_recur == 'yes') {
            // definitely (over)write the rcur field
            civicrm_api3('Membership','create', array(
                'id'                           => $membership_id,
                $config->membership_rcur_field => $contribution_recur_id));
            $this->logMessage("Set membership.{$config->membership_rcur_field} to [{$contribution_recur_id}].", 'debug');
          }

        } elseif ($config->set_contribution_recur == 'fill') {
          // only fill:
          $current_value = civicrm_api('Membership', 'getvalue', array(
              'id'     => $membership_id,
              'return' => $config->membership_rcur_field));
          if ($current_value != $contribution_recur_id) {
            civicrm_api3('Membership','create', array(
                'id'                           => $membership_id,
                $config->membership_rcur_field => $contribution_recur_id));
            $this->logMessage("Set membership.{$config->membership_rcur_field} to [{$contribution_recur_id}].", 'debug');
          }
        }
      }
    }
  }

  /**
   * Extract the membership ID
   *
   * @param $contribution array                           contribution data
   * @param $match        CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher      CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context      CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   * @return int membership ID or 0
   */
  protected function getMembershipID($contribution, $match, $matcher, $context) {
    $config = $this->_plugin_config;
    $membership_id = 0;

    // first stop: check if it's set in the BTX
    if ($config->membership_id) {
      $membership_id = (int) $this->getPropagationValue($context->btx, $match, $config->membership_id);
      if ($membership_id) {
        $this->logMessage("Got membership_id from BTX: {$membership_id}", 'debug');
      }
    }

    // if it's not, check if the contribution had already been assigned to a membership
    if (!$membership_id) {
      $membership_id = (int) CRM_Core_DAO::singleValueQuery("
                  SELECT membership_id 
                    FROM civicrm_membership_payment 
                   WHERE contribution_id = {$contribution['id']};");
      if ($membership_id) {
        $this->logMessage("Got membership_id from MembershipPayment: {$membership_id}", 'debug');
      }
    }

    // still no? see if we can get there by
    if (!$membership_id) {
      if (!empty($config->set_membership_rcur_field) && !empty($contribution['contribution_recur_id'])) {
        try {
          $membership_id = (int)civicrm_api3('Membership', 'getvalue', array(
              $config->set_membership_rcur_field => (int)$contribution['contribution_recur_id'],
              'return' => 'id'));
          if ($membership_id) {
            $this->logMessage("Got membership_id from membership.{$config->set_membership_rcur_field}: {$membership_id}", 'debug');
          }
        } catch (Exception $ex) {
          $this->logMessage("Couldn't get membership_id from membership.{$config->set_membership_rcur_field}.", 'debug');
        }
      }
    }

    // resolve the setting to a value
    return $membership_id;
  }


  /**
   * Extract the recurring contribution ID
   *
   * @param $contribution  array                           contribution data
   * @param $membership_id int                             contribution data
   * @param $match         CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher       CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context       CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   * @return int recurring contribution ID or 0
   */
  protected function getContributionRecurID($contribution, $membership_id, $match, $matcher, $context) {
    $config = $this->_plugin_config;
    $contribution_recur_id = 0;

    // first stop: check if it's set in the BTX
    if ($config->contribution_recur_id) {
      $contribution_recur_id = (int) $this->getPropagationValue($context->btx, $match, $config->contribution_recur_id);
      if ($contribution_recur_id) {
        $this->logMessage("Got contribution_recur_id from BTX: {$contribution_recur_id}", 'debug');
      }
    }

    // second stop: get from contribution
    if (!$contribution_recur_id && !empty($contribution['contribution_recur_id'])) {
      $contribution_recur_id = (int) $contribution['contribution_recur_id'];
      $this->logMessage("Got contribution_recur_id from contribution: {$contribution_recur_id}", 'debug');
    }

    // third stop: get from membership
    if (!$contribution_recur_id && $membership_id && $config->membership_rcur_field) {
      try {
        $contribution_recur_id = (int) civicrm_api3('Membership', 'getvalue', array(
            'id'     => $membership_id,
            'return' => $config->membership_rcur_field));
        if ($contribution_recur_id) {
          $this->logMessage("Got contribution_recur_id from membership.{$config->set_membership_rcur_field}: {$contribution_recur_id}", 'debug');
        }
      } catch (Exception $ex) {
        $this->logMessage("Couldn't get contribution_recur_id from membership.{$config->set_membership_rcur_field}", 'debug');
      }
    }

    return $contribution_recur_id;
  }


  /**
   * deliver the first of the eligible contributions
   * overwrites parent::getFirstContribution()
   */
  protected function getFirstContribution($context) {
    $contributions = $this->getEligibleContributions($context);
    if (empty($contributions)) {
      return NULL;
    } else {
      return reset($contributions);
    }
  }

  /**
   * Extract the membership ID from the BTX
   */
  protected function getEligibleContributions($context) {
    $cache_key = "{$this->_plugin_id}_eligiblecontributions_{$context->btx->id}";
    $cached_result = $context->getCachedEntry($cache_key);
    if ($cached_result !== NULL) return $cached_result;

    $connected_contribution_ids = $this->getContributionIDs($context);
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
      $contribution_query['financial_type_id'] = array('IN' => $config->financial_type_ids);
    }

    // add status ids
    if (!empty($config->contribution_status_ids && is_array($config->contribution_status_ids))) {
      $contribution_query['contribution_status_id'] = array('IN' => $config->contribution_status_ids);
    }

    // add status ids
    if (!empty($config->payment_instrument_ids && is_array($config->payment_instrument_ids))) {
      $contribution_query['payment_instrument_id'] = array('IN' => $config->payment_instrument_id);
    }

    // add return clause
    if (!is_array($config->contribution_fields_required)) {
      $config->contribution_fields_required = [];
    }
    $config->contribution_fields_required[] = 'id';
    $config->contribution_fields_required[] = 'contribution_recur_id';
    $config->contribution_fields_required[] = 'payment_instrument_id';
    $contribution_query['return'] = implode(',', $config->contribution_fields_required);

    // query DB
    $this->logMessage("Find eligible contributions: " . json_encode($contribution_query), 'debug');
    $result = civicrm_api3('Contribution', 'get', $contribution_query);
    $contributions = array();

    foreach ($result['values'] as $contribution) {
      if (!empty($config->payment_instrument_ids_exclude && is_array($config->payment_instrument_ids_exclude))) {
        // check if we need to exclude it because of the payment instrument ID
        if (in_array($contribution['payment_instrument_id'], $config->payment_instrument_ids_exclude)) {
          $this->logMessage("Exclude contribution [{$contribution['id']}] for the payment instrument.", 'debug');
          continue;
        }
      }
      $contributions[] = $contribution;
    }

    // cache result
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }
}
