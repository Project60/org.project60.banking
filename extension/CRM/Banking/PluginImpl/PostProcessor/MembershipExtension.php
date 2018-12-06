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
 * This PostProcessor will connect the generated/matched contribution
 * with a membership
 */
class CRM_Banking_PluginImpl_PostProcessor_MembershipExtension extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    // preconditions:
    if (!isset($config->financial_type_ids))             $config->financial_type_ids              = [2]; // Membership Dues
    if (!isset($config->contribution_status_ids))        $config->contribution_status_ids         = [1]; // Completed
    if (!isset($config->payment_instrument_ids))         $config->payment_instrument_ids          = NULL;
    if (!isset($config->payment_instrument_ids_exclude)) $config->payment_instrument_ids_exclude  = NULL;

    // how to identify the memberships:
    if (!isset($config->find_via_contact))               $config->find_via_contact               = TRUE;            // consider all memberships with the same contact
    if (!isset($config->find_via_payment))               $config->find_via_payment               = TRUE;            // consider all memberships linked by membership_payment
    if (!isset($config->find_via_btxfield))              $config->find_via_btxfield              = 'membership_id'; // consider all membership IDs in the content of this btx field

    // how to filter the memberships
    if (!isset($config->filter_current))                 $config->filter_current                 = TRUE;  // current memberships only
    if (!isset($config->filter_status))                  $config->filter_status                  = FALSE; // list of status_ids
    if (!isset($config->filter_minimum_amount))          $config->filter_minimum_amount          = TRUE;  // could also be monetary amount
    if (!isset($config->filter_membership_types))        $config->filter_membership_types        = [];    // membership type IDs, empty means all

    // how to extend the membership
    if (!isset($config->extend_by))                      $config->extend_by                      = 'period';   // could also be strtotime offset like "+1 month"
    if (!isset($config->extend_from))                    $config->extend_from                    = 'min';      // could also be 'payment_date' or 'end_date'. 'min' means the minimum of the two

    // create of not found
    if (!isset($config->create_if_not_found))            $config->create_if_not_found            = FALSE;  // do we want to create a membership, if none is found?
    if (!isset($config->create_type_id))                 $config->create_type_id                 = 1;      // membership_type_id to create
    if (!isset($config->create_start_date))              $config->create_start_date              = 'now';  // could also be: 'next_first' or 'last_first'
    if (!isset($config->create_source))                  $config->create_source                  = 'CiviBanking';
  }

  /**
   * Should this postprocessor spring into action?
   * Evaluates the common 'required' fields in the configuration
   *
   * @param $match    CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher  CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context  CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   *
   * @return bool     should the this postprocessor be activated
   */
  protected function shouldExecute(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $contributions = $this->getEligibleContributions($context);
    if (empty($contributions)) {
      $this->logMessage("No eligible contributions found.", "debug");
      return FALSE;
    }

    // pass on to parent to check generic reasons
    return parent::shouldExecute($match, $matcher, $context);
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
        // get memberships
        $memberships = $this->getEligibleMemberships($contribution, $match, $context);
        if (empty($memberships)) {
          // no membership found
          if ($config->create_if_not_found) {
            $this->logMessage("No membership identified for contribution [{$contribution['id']}]. Creating one...", 'debug');
            $this->createMembership($contribution);
          } else {
            $this->logMessage("No membership identified for contribution [{$contribution['id']}].", 'debug');
          }

        } else {
          // memberships found
          if (count($memberships) > 1) {
            $this->logMessage("More than one membership identified for contribution [{$contribution['id']}]. Processing first!", 'debug');
          }

          // extend memberships
          $membership = reset($memberships);
          $this->extendMembership($membership);
        }
      }
    }
  }


  
  /**
   * Get all memberships eligible for extension
   *
   * @param array                          $contribution  contribution data
   * @param CRM_Banking_Matcher_Suggestion $match         the executed match
   * @param CRM_Banking_Matcher_Context    $context       context
   * @return array memberships
   * @throws CiviCRM_API3_Exception
   */
  protected function getEligibleMemberships($contribution, $match, $context) {
    $config = $this->_plugin_config;

    // first: collect potential IDs
    $membership_ids = [];
    $memberships    = [];

    // OPTION 1: FIND VIA CONTRIBUTION CONTACT
    if ($config->find_via_contact) {
      $contact_id = (int) $contribution['contact_id'];
      if ($contact_id) {
        try {
          $contacts_memberships = civicrm_api3('Membership', 'get', [
              'contact_id'   => $contact_id,
              'return'       => 'id',
              'option.limit' => 0]);
          foreach ($contacts_memberships['values'] as $contacts_membership) {
            $membership_ids[] = $contacts_membership['id'];
          }
        } catch(Exception $ex) {
          $this->logMessage("Find memberships by contact failed: " . $ex->getMessage(), 'warn');
        }
      }
    }

    // OPTION 2: FIND VIA MEMBERSHIP PAYMENT LINK
    if ($config->find_via_payment) {
      $contribution_id = (int) $contribution['id'];
      if ($contribution_id) {
        try {
          $contribution_memberships = civicrm_api3('MembershipPayment', 'get', [
              'contribution_id'   => $contribution_id,
              'return'            => 'membership_id',
              'option.limit'      => 0]);
          foreach ($contribution_memberships['values'] as $contacts_membership) {
            $membership_ids[] = $contacts_membership['membership_id'];
          }
        } catch(Exception $ex) {
          $this->logMessage("Find memberships by payment link failed: " . $ex->getMessage(), 'warn');
        }
      }
    }

    // OPTION 2: FIND VIA MEMBERSHIP PAYMENT LINK
    if ($config->find_via_btxfield) {
      $membership_id = (int) $this->getPropagationValue($context->btx, $match, $config->find_via_btxfield);
      if ($membership_id) {
        $membership_ids[] = $membership_id;
      }
    }


    // if we haven't found any IDs, we're done
    if (empty($membership_ids)) {
      return $memberships;
    }

    // NOW: compile membership query
    $membership_query['id'] = ['IN' => $membership_ids];
    $membership_query['option.limit'] = 0;

    if ($config->filter_current) {
      $membership_query['status_id'] = ['IN' => self::getCurrentStatusIDs()];
    }

    if (!empty($config->filter_status)) {
      if (is_array($config->filter_status)) {
        $membership_query['status_id'] = ['IN' => $config->filter_status];
      } else {
        $this->logMessage("Configuration option 'filter_status' is not an array! Ignored", 'warn');
      }
    }

    if (!empty($config->filter_membership_types)) {
      if (is_array($config->filter_membership_types)) {
        $membership_query['membership_type_id'] = ['IN' => $config->filter_membership_types];
      } else {
        $this->logMessage("Configuration option 'filter_membership_types' is not an array! Ignored", 'warn');
      }
    }

    // FINALLY: LOAD THE MEMBERSHIPS AND FILTER THEM SOME MORE
    $memberships_found = civicrm_api3('Membership', 'get', $membership_query)['values'];
    foreach ($memberships_found as $membership_found) {
      if ($config->filter_minimum_amount === TRUE) {
        // compare with the membership type's minimum amount
        $membership_type = self::getMembershipType($membership_found['membership_type_id']);
        $minimum_fee = empty($membership_type['minimum_fee']) ? 0.00 : $membership_type['minimum_fee'];
        if ($contribution['total_amount'] < $minimum_fee) {
          $this->logMessage("Contribution [{$contribution['id']}] less than minimal fee", 'debug');
          continue;
        }

      } elseif ($config->filter_minimum_amount > 0) {
        // compare with the given amount
        if ($contribution['total_amount'] < $config->filter_minimum_amount) {
          $this->logMessage("Contribution [{$contribution['id']}] amount too low.", 'debug');
          continue;
        }
      }

      $memberships[] = $membership_found;
    }

    // finally: return our findings
    return $memberships;
  }


  /**
   * Get all eligible contributions wrt the provided filter criteria
   *
   * @param CRM_Banking_Matcher_Context $context
   * @return array contributions
   * @throws CiviCRM_API3_Exception
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
