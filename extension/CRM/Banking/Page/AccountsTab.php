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

    
require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_AccountsTab extends CRM_Core_Page {
  function run() {
    if (isset($_REQUEST['cid'])) {
        $contact_id = (int) $_REQUEST['cid'];
        $query = "
            SELECT 
                ba.id as ba_id,
                ref.reference,
                ref.reference_type_id,
                ba.data_parsed
            FROM 
                civicrm_bank_account ba
            LEFT JOIN 
                civicrm_bank_account_reference ref ON ref.ba_id = ba.id
            WHERE 
                ba.contact_id = $contact_id;
            ";
        $dao = CRM_Core_DAO::executeQuery($query);

        $types = array();
        $results = array();
        while ($dao->fetch()) {
            if (!isset($results[$dao->ba_id])) {
                $info = json_decode($dao->data_parsed, true);
                ksort($info);
                $results[$dao->ba_id] = array(
                    'id' => $dao->ba_id,
                    'data_parsed' => $info,
                    'references' => array());
            }
            
            array_push($results[$dao->ba_id]['references'], array(
                    'reference' => $dao->reference,
                    'reference_type' => $this->lookup_type($types, $dao->reference_type_id),
                ));
        }

        $this->assign('results', $results);
        $this->assign('contact_id', $contact_id);

        // look up IBAN reference type
        $result = civicrm_api('OptionValue', 'getsingle', array('version' => 3, 'name' => 'IBAN', 'value' => 'IBAN'));
        $this->assign('iban_type_id', $result['id']);
    }
    parent::run();
  }

  function lookup_type($types, $type_id) {
    if (!isset($types[$type_id])) {
        // use the api to look up the $type ID
        $result = civicrm_api('OptionValue', 'get', array('version' => 3, 'id' => $type_id));
        if (isset($result['is_error']) && $result['is_error'] || $result['count']==0) {
            $types[$type_id] = "Error";
        } else {
            $types[$type_id] = $result['values'][$result['id']]['name'];
        }
    }
    return $types[$type_id];
  }
}
