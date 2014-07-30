<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
    
require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_AccountSearch extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Find Accounts'));

    if (isset($_REQUEST['reference_partial'])) {
        // this is a search -> do it!
        $query_param = $_REQUEST['reference_partial'];
        if (isset($_REQUEST['full_search'])) {
            $extended_search = "OR ba.data_parsed LIKE \"%$query_param%\"";
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
                civicrm_bank_account_reference ref, 
                civicrm_bank_account ba, 
                civicrm_contact contact
            WHERE 
                    ref.ba_id = ba.id 
                AND ba.contact_id = contact.id
                AND (ref.reference LIKE \"%$query_param%\" $extended_search)
            GROUP BY
                contact.id;
            ";      // add LIMIT 0, 50;
        $dao = CRM_Core_DAO::executeQuery($query);

        $types = array();
        $results = array();
        while ($dao->fetch()) {
            array_push($results, 
                array(
                    'display_name' => $dao->display_name,
                    'contact_type' => $dao->contact_type,
                    'contact_link' => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$dao->id),
                    'reference' => $dao->reference,
                    'reference_type' => $this->lookup_type($types, $dao->reference_type_id),
                    'data_parsed' => json_decode($dao->data_parsed),
                    ));
        }
        $this->assign('results', $results);
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
