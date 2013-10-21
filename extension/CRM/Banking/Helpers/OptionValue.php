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

/**
 * looks up an option group ID
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optiongroupid_by_name($group_name) {
    $result = civicrm_api('OptionGroup', 'get', array('version' => 3, 'name' => $group_name));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::fatal(sprintf(ts("Error while looking up option group '%s'!"), $group_name));
      return 0;
    }

    if (!isset($result['id'])) {
        CRM_Core_Error::warn(sprintf(ts("Couldn't find group '%s'!"), $group_name));
        return 0;    
    }

    return $result['id'];
}


/**
 * looks up an option value
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optionvalueid_by_name($group_id, $value_name) {
    $result = civicrm_api('OptionValue', 'get', array('version' => 3, 'name' => $value_name, 'option_group_id' => $group_id));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::fatal(sprintf(ts("Error while looking up option value '%s'!"), $value_name));
      return 0;
    }

    if (!isset($result['id'])) {
        CRM_Core_Error::warn(sprintf(ts("Couldn't find value '%s'!"), $value_name));
        return 0;    
    }

    return $result['id'];
}

/**
 * looks up an option value
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optionvalue_by_name($group_id, $value_name) {
    $result = civicrm_api('OptionValue', 'get', array('version' => 3, 'name' => $value_name, 'option_group_id' => $group_id));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Error::fatal(sprintf(ts("Error while looking up option value '%s'!"), $value_name));
      return 0;
    }

    if (!isset($result['id'])) {
        CRM_Core_Error::warn(sprintf(ts("Couldn't find value '%s'!"), $value_name));
        return 0;    
    }

    return $result['values'][$result['id']]['value'];
}

/**
 * looks up an option value ID by group name and value name
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optionvalueid_by_groupname_and_name($group_name, $value_name) {
    $group_id = banking_helper_optiongroupid_by_name($group_name);
    if ($group_id) {
        return banking_helper_optionvalueid_by_name($group_id, $value_name);
    } else {
        return 0;
    }
}

/**
 * looks up an option value by group name and value name
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optionvalue_by_groupname_and_name($group_name, $value_name) {
    $group_id = banking_helper_optiongroupid_by_name($group_name);
    if ($group_id) {
        return banking_helper_optionvalue_by_name($group_id, $value_name);
    } else {
        return 0;
    }
}

/**
 * creates an id/name => object mapping for the given option group
 * 
 * the implementation is probably not optimal, but it'll do for the moment
 * 
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
function banking_helper_optiongroup_id_name_mapping($group_name) {
    $group_id = banking_helper_optiongroupid_by_name($group_name);

    if ($group_id) {
        $result = civicrm_api('OptionValue', 'get', array('version' => 3, 'option_group_id' => $group_id));
        if (isset($result['is_error']) && $result['is_error']) {
            CRM_Core_Error::fatal(sprintf(ts("Error while looking up option values for group '%s'!"), $group_id));
            return array();
        }
        $mapping = array();
        foreach ($result['values'] as $entry) {
            $mapping[$entry['id']] = $entry;
            $mapping[$entry['name']] = $entry;
        }

        // inject 'new' value as id 0 for convenience
        $mapping[0] = $mapping['new'];

        return $mapping;

    } else {
        return array();
    }
}