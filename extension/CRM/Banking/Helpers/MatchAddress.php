<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 P. Delbar                      |
| Author: P. Delbar                                      |
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
 * This class assists in determining the address which matches this bank account's 
 * address, if one is specified. It serves the purpose of discovering 'who' 
 * (which Contact) is the owner of this bank account
 *
 * @deprecated ?
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
    if (isset($this->_data_parsed['street_address']) && !empty($this->_data_parsed['street_address']) && isset($this->_data_parsed['city']) && !empty($this->_data_parsed['city'])) {
      $data = array(
          'street_address' => $this->_data_parsed['street_address'],
          'city' => $this->_data_parsed['city'],
      );
      $res = civicrm_api3('address', 'get', $data);
//      echo '<pre>';
//      print_r($res);
//      echo '</pre>';
      if ($res['count'] > 0) {
        $addresses = array();
        foreach ($res['values'] as $id => $address) {
          $sMatch = sprintf("<b>%s</b>, %s <b>%s</b>",$address['street_address'], $address['postal_code'], $address['city']);
          $address['matchString'] = $sMatch;
          $addresses[] = $address;
        }
        return $addresses;
      }
    }
    return null;
  }
  
  public function getContactId() {
    return $this->_contact_id;
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