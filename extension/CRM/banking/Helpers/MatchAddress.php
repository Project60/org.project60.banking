<?php

/**
 * This class assists in determining the address which matches this bank account's 
 * address, if one is specified. It serves the purpose of discovering 'who' 
 * (which Contact) is the owner of this bank account
 */
class CRM_Banking_Helpers_MatchAddress {

  protected $_ba;
  protected $_data_raw;
  protected $_data_parsed;
  protected $_contact_id;
  protected $_address_id;

  public function __construct(CRM_Banking_BAO_BankAccount $ba) {
    $this->_ba = $ba;
    $this->_data_parsed = (array) json_decode($ba->data_parsed);
    $this->_data_raw = (array) json_decode($ba->data_raw);
  }

  public function findAddress() {
    $found = false;
    if (isset($this->_data_parsed['street_address']) && !empty($this->_data_parsed['street_address']) && isset($this->_data_parsed['city']) && !empty($this->_data_parsed['city'])) {
      $data = array(
          'street_address' => $this->_data_parsed['street_address'],
          'city' => $this->_data_parsed['city'],
          'version' => 3,
      );
      $res = civicrm_api('address', 'get', $data);
      if ($res['count'] == 1) {
        $address = $res['values'][$res['id']];
        $this->_address_id = $address['id'];
        $this->_contact_id = $address['contact_id'];
        $found = true;
      }
    }
    return $found;
  }

  public function updateDataParsed() {
    $this->_data_parsed['contact_id'] = $this->_contact_id;
    $this->_data_parsed['address_id'] = $this->_address_id;
    // hack : place contact_id as value as if it is certain
    $this->_ba->contact_id = $this->_contact_id;
    $this->_ba->data_parsed = json_encode($this->_data_parsed);
    $this->_ba->save();
  }

}