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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_PostProcessor extends CRM_Banking_PluginModel_BtxBase {

  function __construct($plugin_dao) {
    parent::__construct($plugin_dao);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->require_btx_status_list))      $config->require_btx_status_list = array('processed');
    if (!isset($config->contribution_fields_required)) $config->contribution_fields_required = '';
    if (!isset($config->membership_fields_required))   $config->membership_fields_required = '';
  }

  /**
   * Postprocess the (already executed) match
   *
   * @param $match    the executed match
   * @param $btx      the related transaction
   * @param $matcher  the matcher plugin executed
   * @param $context  the matcher context contains cache data and context information
   *
   * @return array | FALSE | NULL
   *   The result of the execution, or FALSE when it has not been executed, or
   *   NULL when it might have been executed.
   */
  public abstract function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context);

  /**
   * Visualizes the post processing result for the (already executed) match.
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   * @param CRM_Banking_PluginModel_Matcher $matcher
   * @param CRM_Banking_Matcher_Context $context
   * @param array $result
   *
   * @return mixed
   */
  public function visualizeExecutedMatch(CRM_Banking_Matcher_Suggestion $match,  CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context, $result) {
    return E::ts('%1 might have been executed.', [1 => $this->getName()]);
  }

  /**
   * Should this postprocessor spring into action?
   * Evaluates the common 'required' fields in the configuration
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   *   The executed match.
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   The related transaction.
   * @param CRM_Banking_PluginModel_Matcher $matcher
   *   The matcher plugin executed.
   * @param CRM_Banking_Matcher_Context $context
   *    The matcher context contains cache data and context information.
   *
   * @return string | NULL
   *   HTML markup describing what this post processor might/will be doing after
   *   executing the selected match, or NULL when the post processor will
   *   certainly not be process the executed match. If unsure whether the post
   *   processor will process the executed match, return something describing
   *   that uncertainty and only return NULL if it really will not spring into
   *   action.
   */
  public function previewMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context
  ) {
    return $this->shouldExecute(
      $match,
      $matcher,
      $context,
      TRUE
    ) ? '' : NULL;
  }

  /**
   * Determines whether this post processor is to be executed.
   *
   * Evaluates the common 'required' fields in the configuration.
   *
   * @param CRM_Banking_Matcher_Suggestion $match
   *   The executed match.
   * @param CRM_Banking_BAO_BankTransaction $btx
   *   The related transaction.
   * @param CRM_Banking_Matcher_Context $context
   *   The matcher context contains cache data and context information.
   * @param bool $preview
   *   Whether to preview the execution (i.e. not check btx status).
   *
   * @return bool
   *   Whether this postprocessor is to be executed.
   *
   * @throws Exception
   */
  protected function shouldExecute(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    $preview = FALSE
  ) {
    if (!$preview) {
      // check if the btx status is accepted
      $config = $this->_plugin_config;
      $btx_status_name = civicrm_api3(
        'OptionValue',
        'getvalue',
        [
          'return' => 'name',
          'option_group_id' => 'civicrm_banking.bank_tx_status',
          'id' => $context->btx->status_id,
        ]
      );
      if (!in_array($btx_status_name, $config->require_btx_status_list)) {
        // TODO: log: NOT IN STATUS
        $this->logMessage("Not executing, not in status " . json_encode($config->require_btx_status_list), 'debug');
        return FALSE;
      }
    }

    // check required values
    if (!$this->requiredValuesPresent($context->btx)) {
      $this->logMessage("Not executing, required values missing.", 'debug');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Fetch a named propagation object.
   * @see CRM_Banking_PluginModel_BtxBase::getPropagationValue
   */
  public function getPropagationObject($name, $btx) {
    // in this default implementation, no extra objects are provided
    // please overwrite in the plugin implementation
    switch ($name) {
      case 'contribution':
        return $this->getFirstContribution($btx->context);

      case 'membership':
        return $this->getFirstMembership($btx->context);

      case 'contact':
        return $this->getSoleContact();

      default:
        // nothing to do here
        break;
    }
    return parent::getPropagationObject($name, $btx);
  }

  /**
   * Get the ONE contact this transaction has been associated with. If there are
   *  multiple candidates, NULL is returned
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return int      contact_id of the unique contact linked to the transaction, NULL if not exists/unique
   */
  protected function getSoleContactID(CRM_Banking_Matcher_Context $context) {
    $contact_id = NULL;
    $contributions = $this->getContributions($context);
    foreach ($contributions as $contribution) {
      if (empty($contribution['contact_id'])) {
        // log: problem
      }
      if ($contact_id == NULL) {
        $contact_id = $contribution['contact_id'];
      } elseif ($contact_id == $contribution['contact_id']) {
        continue;
      } else {
        // there is more than one contact:
        return NULL;
      }
    }
    return $contact_id;
  }

  /**
   * Get the ONE contact this transaction has been associated with. If there are
   *  multiple candidates, NULL is returned
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return contact  contact data or NULL
   */
  protected function getSoleContact(CRM_Banking_Matcher_Context $context) {
    $contact_id = $this->getSoleContactID($context);
    if ($contact_id) {
      return civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    } else {
      return NULL;
    }
  }

  /**
   * deliver the first of the eligible contributions
   * overwrites parent::getFirstContribution()
   */
  protected function getFirstContribution($context) {
    $contributions = $this->getContributions($context);
    if (empty($contributions)) {
      return NULL;
    } else {
      return reset($contributions);
    }
  }


  /**
   * Get the list of contributions linked to this trxn ID
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return array    contribution IDs
   */
  protected function getContributions(CRM_Banking_Matcher_Context $context) {
    $cache_key = "{$this->_plugin_id}_contributions_{$context->btx->id}";
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

    // add return clause
    if (!empty($config->contribution_fields_required)) {
      $contribution_query['return'] = $config->contribution_fields_required;
    }

    // query DB
    $result = civicrm_api3('Contribution', 'get', $contribution_query);
    $contributions = $result['values'];

    // cache result
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }


  /**
   * Get the first Membership linked the contribution via MembershipPayments
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return array    membership data
   */
  protected function getFirstMembership(CRM_Banking_Matcher_Context $context) {
    $memberships = $this->getMemberships($context);
    if (empty($memberships)) {
      return NULL;
    } else {
      return reset($memberships);
    }
  }

  /**
   * Get the Memberships linked the contribution via MembershipPayments
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return array    memberships data
   */
  protected function getMemberships(CRM_Banking_Matcher_Context $context) {
    $cache_key = "{$this->_plugin_id}_memberships_{$context->btx->id}";
    $cached_result = $context->getCachedEntry($cache_key);
    if ($cached_result !== NULL) return $cached_result;

    $connected_contribution_ids = $this->getContributionIDs($context);
    if (empty($connected_contribution_ids)) {
      return array();
    }

    $membership_search = civicrm_api3('MembershipPayment', 'get', array(
      'contribution_id' => array('IN' => $connected_contribution_ids),
      'option.limit'    => 0,
      'sequential'      => 1));
    $membership2contribution = array();
    foreach ($membership_search['values'] as $membership_payment) {
      if (isset($membership2contribution[$membership_payment['membership_id']])) {
        $membership2contribution[$membership_payment['membership_id']][] = $membership_payment['contribution_id'];
      } else {
        $membership2contribution[$membership_payment['membership_id']] = array($membership_payment['contribution_id']);
      }
    }

    if (!empty($membership2contribution)) {
      $config = $this->_plugin_config;
      $membership_query = array(
        'id'           => array('IN' => array_keys($membership2contribution)),
        'option.limit' => 0,
        'sequential'   => 1);

      // add return clause
      if (!empty($config->membership_fields_required)) {
        $membership_query['return'] = $config->membership_fields_required;
      }

      // query DB
      $result = civicrm_api3('Membership', 'get', $membership_query);
      $memberships = $result['values'];

    } else {
      $memberships = array();
    }

    // cache result
    $context->setCachedEntry($cache_key, $memberships);
    return $memberships;
  }


  /**
   * Get the list of contributions linked to this trxn ID
   *
   * @param $context  the matcher context contains cache data and context information
   *
   * @return array    contribution IDs
   */
  protected function getContributionIDs(CRM_Banking_Matcher_Context $context) {
    $match = $context->getExecutedSuggestion();
    $contribution_ids = array();

    if ($match) {
      // get the single-style ('contribution_id')
      $single_id = $match->getParameter('contribution_id');
      if (is_numeric($single_id)) {
        $contribution_ids[$single_id] = 1;
      }

      // get the multi-style ('contribution_ids')
      $multi_ids = $match->getParameter('contribution_ids');
      if (is_array($multi_ids)) {
        foreach ($multi_ids as $contribution_id) {
          if (is_numeric($contribution_id)) {
            $contribution_ids[$contribution_id] = 1;
          }
        }
      }
    }

    return array_keys($contribution_ids);
  }

  /**
   * Add all given tags to the given contact
   */
  protected function tagContact($contact_id, $tag_names) {
    foreach ($tag_names as $tag_name) {
      $tag = civicrm_api3('Tag', 'get', array(
        'name'     => $tag_name,
        'used_for' => 'civicrm_contact'));
      if (!empty($tag['id'])) {
        civicrm_api3('EntityTag', 'create', array(
          'entity_id'    => $contact_id,
          'entity_table' => 'civicrm_contact',
          'tag_id'       => $tag['id']));
        $this->logMessage("Tagged [{$contact_id}] with '{$tag_name}'.", 'info');
      }
    }
  }
}

