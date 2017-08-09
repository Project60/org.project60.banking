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
 * This PostProcessor will store account information after the match
 */
class CRM_Banking_PluginImpl_PostProcessor_Accounts extends CRM_Banking_PluginModel_PostProcessor {

  // caches the account values
  public static $_account_cache = array();

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->mode))          $config->mode          = 'both'; // or 'debit' or 'credit'
    if (!isset($config->type))          $config->type          = 'reference'; // or 'ba_id'
    if (!isset($config->ref_type))      $config->ref_type      = 'IBAN'; // or any other reference type name
    if (!isset($config->target))        $config->target        = 'contribution'; // or 'contact'
    if (!isset($config->own_account))   $config->own_account   = NULL;
    if (!isset($config->party_account)) $config->party_account = NULL;
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
    $btx = $context->btx;

    // only be active if there are fields to be written into...
    if (empty($config->own_account) && empty($config->party_account)) {
      error_log("No accounts set. Please configure.");
      return FALSE;
    }

    // check if we're in the right mode
    switch (strtolower($config->mode)) {
      case 'debit':
        if ($btx->amount >= 0.0) {
          return FALSE;
        }
        break;

      case 'credit':
        if ($btx->amount < 0.0) {
          return FALSE;
        }
        break;

      default:
      case 'both':
        // this one always works
        break;
    }

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
    if (!$this->shouldExecute($match, $matcher, $context)) {
      // TODO: log: not executing...
      return;
    }

    // compile update
    $config = $this->_plugin_config;
    $update = array();

    if (!empty($config->own_account)) {
      $own_account_reference = $this->getAccountData($context, '_', TRUE);
      if ($own_account_reference) {
        $update[$config->own_account] = $own_account_reference;
      }
    }

    if (!empty($config->party_account)) {
      $party_account_reference = $this->getAccountData($context, '_party_');
      if ($party_account_reference) {
        $update[$config->party_account] = $party_account_reference;
      }
    }

    if (empty($update)) {
      // there's nothing to update
      return;
    }

    // get the entity ID
    $object = $this->getPropagationObject($config->target, $context->btx);
    if (empty($object['id'])) {
      // TODO: log: object $config->target could not be uniquely identified
      return;
    } else {
      $update['id'] = $object['id'];
    }

    // execute update to store the bank accounts
    civicrm_api3($config->target, 'create', $update);
  }

  /**
   * get the desired account data to write into the custom fields
   */
  protected function getAccountData($context, $prefix, $cache = FALSE) {
    $config = $this->_plugin_config;
    $data   = $context->btx->getDataParsed();
    $value  = CRM_Utils_Array::value("{$prefix}{$config->ref_type}", $data);
    if (empty($value)) {
      // no account reference given
      return NULL;
    }

    if ($config->type == 'ba_id') {
      // this means we want the account ID, not just the reference
      $contact_id = $this->getSoleContactID($context);
      if (empty($contact_id)) {
        // we cannot create/find the bank account if there is no contact
        // TODO: log ("NO SINGLE CONTACT");
        return NULL;
      }

      $cache_key = "{$contact_id}-#-{$value}";
      if ($cache && isset(self::$_account_cache[$cache_key])) {
        $values = self::$_account_cache[$cache_key];
      } else {
        // compile get-or-create
        $bank_account_data = array(
          'reference_type' => $config->ref_type,
          'reference'      => $value,
          'contact_id'     => $contact_id);

        // special treatment for IBANs
        if ($config->ref_type == 'IBAN' && !empty($data["{$prefix}BIC"])) {
          $bank_account_data['bic'] = $data["{$prefix}BIC"];
        }

        // run the query
        $bank_account = civicrm_api3('BankingAccount', 'getorcreate', $bank_account_data);
        $value = $bank_account['id'];
        if ($cache) {
          self::$_account_cache[$cache_key] = $value;
        }
      }
    }

    return $value;
  }
}
