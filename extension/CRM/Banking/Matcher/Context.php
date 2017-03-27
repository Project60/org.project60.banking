<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

  public $btx;

  // will store generic attributes from the various matchers
  private $_attributes = array();

  // will store cached data needed/produced by the helper functions
  private $_caches;

  public function __construct( CRM_Banking_BAO_BankTransaction $btx ) {
    $this->btx = $btx;
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
    // we'll start with the findContacts method
    if ($name==NULL) {
      $contacts = array();
    } else {
      $contacts = $this->lookupContactByName($name, $lookup_by_name_parameters);
      //error_log('after lookup:'.print_r($contacts, true));
    }

    // then look for 'contact_id' or 'external_identifier'
    $data_parsed = $this->btx->getDataParsed();
    if (!empty($data_parsed['external_identifier'])) {
      $contact = civicrm_api('Contact', 'getsingle', array('external_identifier' => $data_parsed['external_identifier'], 'version' => 3));
      if (empty($contact['is_error'])) {
        $contacts[$contact['id']] = 1.0;
      }
    }

    // add contact_id if right there...
    if (!empty($data_parsed['contact_id'])) {
      $contacts[$data_parsed['contact_id']] = 1.0;
    }
    //error_log('after direct ident:'.print_r($contacts, true));

    // look up accounts
    $account_owners = $this->getAccountContacts();
    foreach ($account_owners as $account_owner) {
      $contacts[$account_owner] = 1.0;
    }
    //error_log('after accounts:'.print_r($contacts, true));

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
        $account_references = civicrm_api('BankAccountReference', 'get', array('reference' => $data_parsed['party_ba_reference'], 'version' => 3));
        if (empty($account_references['is_error'])) {
          foreach ($account_references['values'] as $account_reference) {
            // then load the respective accounts
            $account = civicrm_api('BankAccount', 'getsingle', array('id' => $account_reference['ba_id'], 'version' => 3));
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
}