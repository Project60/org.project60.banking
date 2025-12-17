<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

declare(strict_types = 1);

/**
 * This matcher use regular expressions to extract information from the payment meta information
 */
class CRM_Banking_PluginImpl_Matcher_AccountLookup extends CRM_Banking_PluginModel_Analyser {

  /**
   * class constructor
   */
  public function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    // phpcs:disable Squiz.PHP.CommentedOutCode.Found
    // one of 'off', 'fill', 'update'
    // phpcs:enable
    if (!isset($config->lookup_org_account_mode)) {
      $config->lookup_org_account_mode = 'off';
    }
    // '/^(_NBAN_..|_IBAN)$/';
    if (!isset($config->lookup_org_account_prefix)) {
      $config->lookup_org_account_prefix = '_';
    }
    // phpcs:disable Squiz.PHP.CommentedOutCode.Found
    // one of 'off', 'fill', 'update'
    // phpcs:enable
    if (!isset($config->lookup_donor_account_mode)) {
      $config->lookup_donor_account_mode = 'update';
    }
    //^(_party_NBAN_..|_party_IBAN)$/';
    if (!isset($config->lookup_donor_account_prefix)) {
      $config->lookup_donor_account_prefix = '_party_';
    }
  }

  /**
   * this matcher does not really create suggestions, but rather enriches the parsed data
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $modified = FALSE;

    // check own bank account
    if ($config->lookup_org_account_mode == 'update') {
      $modified |= $this->setAccount($btx, 'ba_id', $config->lookup_org_account_prefix, $context);
    }
    elseif ($config->lookup_org_account_mode == 'fill'
              && empty($btx->ba_id)) {
      $modified |= $this->setAccount($btx, 'ba_id', $config->lookup_org_account_prefix, $context);
    }

    // check party bank account
    if ($config->lookup_donor_account_mode == 'update') {
      $modified |= $this->setAccount($btx, 'party_ba_id', $config->lookup_donor_account_prefix, $context);
    }
    elseif ($config->lookup_donor_account_mode == 'fill'
              && empty($btx->party_ba_id)) {
      $modified |= $this->setAccount($btx, 'party_ba_id', $config->lookup_donor_account_prefix, $context);
    }

    if ($modified) {
      $btx->save();
    }
  }

  /**
   * This function will look up and set the bank account in the btx entity
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *  bank transaction
   *
   * @param string $ba_attribute
   *  should either be 'ba_id' (owner's account) or 'party_ba_id' (other party's account)
   *
   * @param CRM_Banking_Matcher_Context $context
   *
   * @return bool true iff $btx was changed
   */
  protected function setAccount(CRM_Banking_BAO_BankTransaction $btx, string $ba_attribute, $prefix, CRM_Banking_Matcher_Context $context): bool {
    $data = $btx->getDataParsed();
    $types = $this->getReferenceTypes($context);
    foreach ($types as $type_id => $type_name) {
      if (!empty($data[$prefix . $type_name])) {
        // we have an account reference => look it up
        $ba_id = $this->lookupBankAccount($type_id, $data[$prefix . $type_name], $context);
        if ($ba_id) {
          if ($ba_id != $btx->$ba_attribute) {
            // the account differs => set and return
            $btx->$ba_attribute = $ba_id;
            return TRUE;
          }
          else {
            // the account is the same => return unchanged
            return FALSE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * cached bank account lookup
   */
  protected function lookupBankAccount($type_id, string $reference, CRM_Banking_Matcher_Context $context) : ?string {
    $account_cache = $context->getCachedEntry('analyser_account.cached_references');
    if ($account_cache === NULL) {
      $account_cache = [];
    }

    if (!isset($account_cache[$type_id][$reference])) {
      // look up the account
      try {
        $result = civicrm_api3('BankingAccountReference', 'getsingle', [
          'reference' => $reference,
          'reference_type_id' => $type_id,
        ]);
        if (!empty($result['is_error']) || empty($result['ba_id'])) {
          $account_cache[$type_id][$reference] = NULL;
        } else {
          $account_cache[$type_id][$reference] = $result['ba_id'];
        }
        $context->setCachedEntry('analyser_account.cached_references', $account_cache);
      } catch (CRM_Core_Exception $e) {
        $this->logMessage("Error while looking up bank account reference: " . $e->getMessage());
        return null;
      }

      // finally set the account for the btx
      return $account_cache[$type_id][$reference];
    } else {
      return null;
    }
  }

  /**
   * Get a id => value matching of all reference types
   */
  protected function getReferenceTypes($context) {
    $types = $context->getCachedEntry('analyser_account.reference_types');
    if ($types === NULL) {
      $group_id = banking_helper_optiongroupid_by_name('civicrm_banking.reference_types');
      $types = CRM_Core_OptionGroup::valuesByID($group_id, $flip = TRUE, $grouping = FALSE, $localize = FALSE, $labelColumnName = 'id', $onlyActive = TRUE, $fresh = FALSE);
      $context->setCachedEntry('analyser_account.reference_types', $types);
    }
    return $types;
  }
}
