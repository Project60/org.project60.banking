<?php

class CRM_Banking_PluginModel_Action_CreateContactIfNew {

  public function describe($params, $btx) {
    $ba_id = $btx->party_ba_id;
    $ba = civicrm_api('BankingAccount', 'getsingle', array('version' => 3, 'id' => $ba_id));
    if ($ba['contact_id'] == null)
      return "creating a <b>new contact</b> based on the information in the bank account";
    $con = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $ba['contact_id']));

    return "using the contact identified : <b>" . $con['display_name'] . '</b>';
  }

  public function execute($params, $btx) {
    $ba_id = $btx->party_ba_id;
    $ba = civicrm_api('BankingAccount', 'getsingle', array('version' => 3, 'id' => $ba_id));
    $ba_data_parsed = json_decode($ba['data_parsed'], true);

    // if contact does not exist, create it
    if ($ba['contact_id'] == null) {
      $btx_data_parsed = json_decode($btx->data_parsed, true);
      $params = array(
          'version' => 3,
          'contact_type' => 'Individual',
          'display_name' => $btx_data_parsed['name'],
      );
      $con = civicrm_api('Contact', 'create', $params);

      civicrm_api('BankingAccount', 'update', array('version' => 3, 'id' => $ba_id,'contact_id' => $con['id']));
      
      // if no BA address exists, create one
      if (!isset($ba_data_parsed['address_id'])) {
        $params = array(
            'version' => 3,
            'contact_id' => $con['id'],
            'location_type_id' => 6,
            'street_address' => $ba_data_parsed['street_address'],
            'postal_code' => $ba_data_parsed['postal_code'],
            'city' => $ba_data_parsed['city'],
            'is_primary' => 1,
            );
        civicrm_api('Address', 'create', $params);
     }
    }
  }

}
