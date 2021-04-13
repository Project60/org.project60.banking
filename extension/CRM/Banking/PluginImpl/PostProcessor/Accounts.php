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
    if (!isset($config->mode))           $config->mode           = 'both'; // or 'debit' or 'credit'
    if (!isset($config->type))           $config->type           = 'reference'; // or 'ba_id'
    if (!isset($config->ref_type))       $config->ref_type       = 'IBAN'; // or any other reference type name
    if (!isset($config->target))         $config->target         = 'contribution'; // or 'contact', or 'createonly'
    if (!isset($config->own_contact_id)) $config->own_contact_id = 1; // TODO: use domain default contact?
    if (!isset($config->own_account))    $config->own_account    = NULL;
    if (!isset($config->party_account))  $config->party_account  = NULL;
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
    $btx = $context->btx;

    // only be active if there are fields to be written into...
    if (empty($config->own_account) && empty($config->party_account)) {
      error_log("No target variables set. Please configure.");
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
    return parent::shouldExecute($match, $matcher, $context, $preview);
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
      $this->logMessage("Accounts PostProcessor not executing", 'info');
      return;
    }

    // compile update
    $config = $this->_plugin_config;
    $update = array();

    if (!empty($config->own_account)) {
      $own_account_reference = $this->getAccountData($context, $config->own_contact_id, '_', TRUE);
      if ($own_account_reference) {
        $update[$config->own_account] = $own_account_reference;
      }
    }

    if (!empty($config->party_account)) {
      $contact_id = $this->getSoleContactID($context);
      $party_account_reference = $this->getAccountData($context, $contact_id, '_party_');
      if ($party_account_reference) {
        $update[$config->party_account] = $party_account_reference;
      }
    }

    if (empty($update)) {
      // there's nothing to update
      return;
    }

    // get the entity ID
    if ($config->target == 'createonly') {
      // 'createonly' means: just store the BA with the contact,
      //   which is already done by the getAccountData() calls
      //   => nothing to do here
    } else {
      // now if this is a proper entity, we'll have to store it
      $object = $this->getPropagationObject($config->target, $context->btx);
      if (empty($object['id'])) {
        $this->logMessage("Related object '{$config->target}' could not be (uniquely) identified.", 'warn');
        return;
      } else {
        $update['id'] = $object['id'];
      }

      // execute update to store the bank accounts
      $this->logMessage("Accounts Post Processor calling {$config->target}.create: " . json_encode($update), 'debug');
      civicrm_api3($config->target, 'create', $update);
    }
  }

  /**
   * get the desired account data to write into the custom fields
   */
  protected function getAccountData($context, $contact_id, $prefix, $cache = FALSE) {
    $config = $this->_plugin_config;
    $data   = $context->btx->getDataParsed();
    $value  = CRM_Utils_Array::value("{$prefix}{$config->ref_type}", $data);
    if (empty($value)) {
      // no account reference given
      return NULL;
    }

    if ($config->type == 'ba_id') {
      // this means we want the account ID, not just the reference
      if (empty($contact_id)) {
        // we cannot create/find the bank account if there is no contact
        $this->logMessage("No (single) contact associated.", 'warn');
        return NULL;
      }

      $cache_key = "{$contact_id}-#-{$value}";
      if ($cache && isset(self::$_account_cache[$cache_key])) {
        $value = self::$_account_cache[$cache_key];
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
