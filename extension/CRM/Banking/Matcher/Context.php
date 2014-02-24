<?php

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
   * Will provide a name based lookup for contacts
   *
   * @return array(contact_id => similarity), where similarity is from [0..1]
   */
  public function lookupContactByName($name, $parameters=array()) {
    $parameters = (array) $parameters;
    
    if (!$name) {
      // no name given, no results:
      return array();
    }

    // check the cache first
    $cache_key = "banking.matcher.context.name_lookup.$name";
    $contacts_found = $this->getCachedEntry($cache_key);
    if ($contacts_found!=NULL) {
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

  	return $contacts_found;
  }

  /**
   * If the payment was associated with a (source) account, this
   *  function looks up the account's owner contact ID
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