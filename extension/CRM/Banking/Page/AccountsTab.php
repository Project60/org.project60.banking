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
                civicrm_bank_account_reference ref, 
                civicrm_bank_account ba
            WHERE 
                ba.contact_id = $contact_id
            AND ref.ba_id = ba.id;
            ";
        $dao = CRM_Core_DAO::executeQuery($query);

        $types = array();
        $results = array();
        while ($dao->fetch()) {
            if (!isset($results[$dao->ba_id])) {
                $results[$dao->ba_id] = array(
                    'data_parsed' => json_decode($dao->data_parsed),
                    'references' => array());
            }
            
            array_push($results[$dao->ba_id]['references'], array(
                    'reference' => $dao->reference,
                    'reference_type' => $this->lookup_type($types, $dao->reference_type_id),
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
