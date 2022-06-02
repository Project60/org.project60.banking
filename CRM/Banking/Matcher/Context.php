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

class CRM_Banking_Matcher_Context {

  // reference to the BTX being processed
  public $btx;

  // set to the executed suggestion
  protected $executed_suggestion = NULL;

  // will store generic attributes from the various matchers
  private $_attributes = array();

  // will store cached data needed/produced by the helper functions
  private $_caches;

  protected $bank_account_reference_matching_probability = null;

  public function __construct( CRM_Banking_BAO_BankTransaction $btx ) {
    $this->btx = $btx;
    $btx->context = $this;

    $this->bank_account_reference_matching_probability = CRM_Core_BAO_Setting::getItem('CiviBanking', 'reference_matching_probability');
    if ($this->bank_account_reference_matching_probability === null) {
      $this->bank_account_reference_matching_probability = 1.0;
    }
  }

  /**
   * Provides a general interface to looking up contacts matching the current payment.
   * This includes:
   *  - looking up 'contact_id' or 'external_identifier' values in the paresed_data
   *  - looking up identified account owners
   *  - name based search via lookupContactByName() method
   *
   * If more than one contact has probability 1, then it will be reduced to 0.99
   *
   * @return array(contact_id => similarity), where similarity is from [0..1]
   */
  public function findContacts($threshold=0.0, $name=NULL, $lookup_by_name_parameters=array()) {
    $lookup_by_name_parameters = (array) $lookup_by_name_parameters;

    // we'll start with the findContacts method
    if (!empty($lookup_by_name_parameters['mode']) && $lookup_by_name_parameters['mode']=='off') {
      // search turned off
      $contacts = array();
    } elseif (empty($name)) {
      // no name given
      $contacts = array();
    } else {
      // all good, let's go:
      $contacts = $this->lookupContactByName($name, $lookup_by_name_parameters);
    }

    // then look for 'contact_id' or 'external_identifier'
    $data_parsed = $this->btx->getDataParsed();
    if (!empty($data_parsed['external_identifier'])) {
      // RUN SQL query instead of API (see BANKING-165)
      $contact_id = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_contact WHERE external_identifier = %1 AND is_deleted=0;',
          array(1 => array($data_parsed['external_identifier'], 'String')));
      if ($contact_id) {
        $contacts[$contact_id] = 1.0;
      }
    }

    // add contact_id if right there...
    if (!empty($data_parsed['contact_id'])) {
      $contacts[$data_parsed['contact_id']] = 1.0;
    }

    // look up accounts
    $account_owners = $this->getAccountContacts();
    foreach ($account_owners as $account_owner) {
      $contacts[$account_owner] = $this->bank_account_reference_matching_probability;
    }

    // check if multiple 1.0 probabilities are there...
    $perfect_match_count = 0;
    foreach ($contacts as $contact => $probability) {
      if ($probability == 1.0) $perfect_match_count++;
    }
    if ($perfect_match_count > 1) {
      // in this case, we reduce each probability to 0.99
      foreach ($contacts as $contact => $probability) {
        if ($probability == 1.0) $contacts[$contact] = 0.99;
      }
    }

    // remove all that are under the threshold
    $selected_contacts = array();
    foreach ($contacts as $contact => $probability) {
      if ($probability >= $threshold) {
        $selected_contacts[$contact] = $probability;
      }
    }

    // remove contacts that are in trash
    $selected_contacts = $this->filterDeletedContacts($selected_contacts);

    // now sort by probability and return
    arsort($selected_contacts);
    return $selected_contacts;
  }

  /**
   * Will provide a name based lookup for contacts
   *
   * @return array(contact_id => similarity), where similarity is from [0..1]
   */
  public function lookupContactByName($name, $parameters=array()) {
    $logger = CRM_Banking_Helpers_Logger::getLogger();
    $logger->setTimer('lookupContactByName');
    $parameters = (array) $parameters;

    if (!$name) {
      // no name given, no results:
      return array();
    }

    // check the cache first (key has md5 due to different parameters)
    $cache_key = "banking.matcher.context.name_lookup.".md5(serialize($name).serialize($parameters));
    $contacts_found = $this->getCachedEntry($cache_key);
    if ($contacts_found!=NULL) {
      $logger->logDebug("lookupContactByName: cache hit");
      return $contacts_found;
    } else {
      $contacts_found = array();
    }

    // call the lookup function (API)
    $parameters['version'] = 3;
    $parameters['name'] = $name;
    if (isset($parameters['modifiers']))
      $parameters['modifiers'] = json_encode($parameters['modifiers']);
    $result = civicrm_api('BankingLookup', 'contactbyname', $parameters);
    if (isset($result['is_error']) && $result['is_error']) {
      // TODO: more error handling?
      error_log("org.project60.banking: BankingLookup:contactbyname failed with: ".$result['error_message']);
    } else {
      $contacts_found = $result['values'];
    }

    // update the cache
    $this->setCachedEntry($cache_key, $contacts_found);

    $logger->logTime('lookupContactByName', 'lookupContactByName');
  	return $contacts_found;
  }


  /**
   * If the payment was associated with a (source) account, this
   *  function looks up the account's owner(s) contact ID(s)
   */
  public function getAccountContacts() {
    $contact_ids = $this->getCachedEntry('_account_contact_ids');
    if ($contact_ids===NULL) {
      // first, get the account contact
      $contact_ids = array();
      $btx_account_contact = $this->getAccountContact();
      if ($btx_account_contact) array_push($contact_ids, $btx_account_contact);

      // then, look up party_ba_reference...
      $data_parsed = $this->btx->getDataParsed();
      if (!empty($data_parsed['party_ba_reference'])) {
        // find all accounts references matching the parsed data
        $account_references = civicrm_api('BankingAccountReference', 'get', array('reference' => $data_parsed['party_ba_reference'], 'version' => 3));
        if (empty($account_references['is_error'])) {
          foreach ($account_references['values'] as $account_reference) {
            // then load the respective accounts
            $account = civicrm_api('BankingAccount', 'getsingle', array('id' => $account_reference['ba_id'], 'version' => 3));
            if (empty($account['is_error'])) {
              // and add the owner
              array_push($contact_ids, $account['contact_id']);
            }
          }
        }
      }

      $this->setCachedEntry('_account_contact_ids', $contact_ids);
    }
    return $contact_ids;
  }

  /**
   * If the payment was associated with a (source) account, this
   *  function looks up the account's owner contact ID
   * @deprecated use getAccountContacts()
   */
  public function getAccountContact() {
    $contact_id = $this->getCachedEntry('_account_contact_id');
    if ($contact_id===NULL) {
      if ($this->btx->party_ba_id) {
        $account = new CRM_Banking_BAO_BankAccount();
        $account->get('id', $this->btx->party_ba_id);
        if ($account->contact_id) {
          $contact_id = $account->contact_id;
        } else {
          $contact_id = 0;
        }
      } else {
        $contact_id = 0;
      }
      $this->setCachedEntry('_account_contact_id', $contact_id);
    }
    return $contact_id;
  }

  /**
   * Remove such contacts from the list, that are in trash (is_deleted = 1)
   * @param $contact2probablility array contact_id => probability
   * @return array contact_id => probability
   */
  public function filterDeletedContacts($contact2probablility) {
    // if empty, there's nothing to do
    if (empty($contact2probablility)) {
      return $contact2probablility;
    }

    // check if this was cached
    $cache_key = '_filtered_list_' . sha1(serialize($contact2probablility));
    $filtered_list = $this->getCachedEntry($cache_key);
    if ($filtered_list === NULL) {
      $filtered_list = [];
      $result = civicrm_api3('Contact', 'get', [
          'id'         => ['IN' => array_keys($contact2probablility)],
          'is_deleted' => 0,
          'return'     => 'id',
          'sequential' => 1]);
      foreach ($result['values'] as $contact) {
        $filtered_list[$contact['id']] = $contact2probablility[$contact['id']];
      }
      $this->setCachedEntry($cache_key, $filtered_list);
    }
    return $filtered_list;
  }

  /**
   * Will check if the given key is set in the cache
   *
   * @return the previously stored value, or NULL
   */
  public function getCachedEntry($key) {
    if (isset($this->_caches[$key])) {
      return $this->_caches[$key];
    } else {
      return NULL;
    }
  }

  /**
   * Set the given cache value
   */
  public function setCachedEntry($key, $value) {
    $this->_caches[$key] = $value;
  }

  /**
   * Set the executed suggestion
   */
  public function setExecutedSuggestion($suggestion) {
    $this->executed_suggestion = $suggestion;
  }

  /**
   * Get the executed suggestion.
   * Will be NULL if non has been executed yet
   */
  public function getExecutedSuggestion() {
    return $this->executed_suggestion;
  }

  /**
   * remove the internal values, so the GC can pick it up
   */
  public function destroy() {
    $this->btx = NULL;
    $this->executed_suggestion = NULL;
    $this->_caches = array();
    $this->_attributes = array();
  }
}
