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
    $result = civicrm_api3('OptionGroup', 'get', array('name' => $group_name));

    if (empty($result['id'])) {
      CRM_Core_Error::debug_log_message("org.project60.banking: Couldn't find option group '{$group_name}'!");
      return 0;
    } else {
      return $result['id'];
    }
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
    $result = civicrm_api3('OptionValue', 'get', array(
        'name'            => $value_name,
        'option_group_id' => $group_id));

  if (empty($result['id'])) {
    CRM_Core_Error::debug_log_message("org.project60.banking: Couldn't find option value '{$value_name}'!");
    return 0;
  } else {
    return $result['id'];
  }
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
    $result = civicrm_api3('OptionValue', 'get', array(
        'name'            => $value_name,
        'option_group_id' => $group_id));

    if (empty($result['id'])) {
      CRM_Core_Error::debug_log_message("org.project60.banking: Couldn't find option value '{$value_name}'!");
      return 0;
    } else {
      return $result['values'][$result['id']]['value'];
    }
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
        $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => $group_id));
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

/**
 * will check if the given tx_status_id is closed,
 *  i.e. marked as 'processed' or 'ignored'
 *
 * @return TRUE if closed, FALSE otherwise
 */
function banking_helper_tx_status_closed($tx_status_id) {
    $status = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
    return $status[$tx_status_id]['name']=='processed' 
        || $status[$tx_status_id]['name']=='ignored';
}