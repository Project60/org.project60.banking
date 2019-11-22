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

use CRM_Banking_ExtensionUtil as E;

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_AccountSearch extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Find Accounts'));

    if (isset($_REQUEST['reference_partial']) && $_REQUEST['reference_partial'] != '') {
        // this is a search -> do it!
        $query_param = $_REQUEST['reference_partial'];
        if (isset($_REQUEST['full_search'])) {
            $extended_search = "OR ba.data_parsed LIKE \"%{$query_param}%\"";
        } else {
            $extended_search = "";
        }

        // build query
        $query = "
            SELECT 
                contact.id, 
                contact.display_name,
                contact.contact_type,
                ref.reference,
                ref.reference_type_id,
                ba.data_parsed
            FROM 
                civicrm_bank_account_reference AS ref, 
                civicrm_bank_account AS ba, 
                civicrm_contact AS contact
            WHERE 
                    ref.ba_id = ba.id 
                AND ba.contact_id = contact.id
                AND (ref.reference LIKE \"%{$query_param}%\" $extended_search)
            GROUP BY
                contact.id,
                ref.reference,
                ref.reference_type_id,
                ba.data_parsed;
            ";      // add LIMIT 0, 50;
        $dao = CRM_Core_DAO::executeQuery($query);

        $types = array();
        $results = array();
        while ($dao->fetch()) {
            array_push($results, 
                array(
                    'display_name'   => $dao->display_name,
                    'contact_type'   => $dao->contact_type,
                    'contact_link'   => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $dao->id),
                    'reference'      => $dao->reference,
                    'reference_type' => $this->lookup_type($types, $dao->reference_type_id),
                    'data_parsed'    => json_decode($dao->data_parsed, TRUE),
                    ));
        }
        $this->assign('results', $results);
    } else {
      $this->assign('results', array());
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
