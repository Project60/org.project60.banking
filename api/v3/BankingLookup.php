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


/**
 * File for the CiviCRM APIv3 banking lookup services
 *
 * @package CiviBanking Extension
 */


/**
 * Will provide a name based lookup for contacts. It is designed to take care of
 *  'tainted' string, i.e. containing abbreviations, initals, titles, etc.
 *
 * @param mode                    which mode to use (default: 'getquick')
 *                                  'getquick' - use Contact.getquick API call
 *                                  'sql'      - use SQL query
 *                                  'off'      - turn the search off completely
 * @param exact_match_wins        if this is set, the search is cut short if a exact match is found
 * @param name                    the name string to look for
 * @param modifiers               are used to modfiy the string: expects a JSON encoded list
 *                                  of arrays with the following entries:
 *                                  'search':     regex to search (for preg_replace)
 *                                  'replace':    string to replace it with (for preg_replace)
 *                                  'mode':       'entire' (apply to the whole string)
 *                                                'alternative'  (apply to each component, but also keep original)
 * @param hard_cap                will truncate the result list at given index
 * @param hard_cap_probability    will truncate the result list at given probability
 * @param soft_cap_probability    will purge all entries with less that the given probability
 *                                    but will keep the first [soft_cap_min] entries if given
 * @param soft_cap_min            see soft_cap_probability
 *
 * @return array(contact_id => probability), where probability is from [0..1)
 */
function civicrm_api3_banking_lookup_contactbyname($params) {
  if (!isset($params['name']) || empty($params['name'])) {
    // no name given, no results:
    return civicrm_api3_create_error("No 'name' parameter given.");
  }

  $name = strtolower($params['name']);

  // get modifiers
  if (!isset($params['modifiers']) || empty($params['modifiers'])) {
    $modifiers = array();
  } else {
    $modifiers = json_decode($params['modifiers'], true);
  }

  // apply 'entire'-modifiers to string
  foreach ($modifiers as $modifier) {
    if ((!empty($modifier['search'])) && (!empty($modifier['replace'])) && ((empty($modifier['mode']) || $modifier['mode'] == 'entire'))) {
      $name = preg_replace($modifier['search'], $modifier['replace'], $name);
    }
  }

  // chop up the name string
  $name_bits = preg_split("( |,|&|\.)", $name, 0, PREG_SPLIT_NO_EMPTY);

  // apply 'alternative'-modifiers to string
  foreach ($name_bits as $name_bit) {
    foreach ($modifiers as $modifier) {
      if ((!empty($modifier['search'])) && (!empty($modifier['replace'])) && (!empty($modifier['mode'])) && ($modifier['mode'] == 'alternative')) {
        $modified_name_bit = preg_replace($modifier['search'], $modifier['replace'], $name_bit);
        if ($modified_name_bit != $name_bit) {
          $name_bits[] = $modified_name_bit;
        }
      }
    }
  }

  // keep only the longest 4 entries (for performance reasons)
  while (count($name_bits)>4) {
    $shortest_index = 0;
    $shortest_length = strlen($name_bits[0]);
    for ($i=1; $i < count($name_bits); $i++) {
      if (strlen($name_bits[$i]) < $shortest_length) {
        $shortest_length = strlen($name_bits[$i]);
        $shortest_index = $i;
      }
    }
    unset($name_bits[$shortest_index]);
    $name_bits = array_values($name_bits);
  }

  // prepare all possible 2-tuples to feed to quicksearch
  $name_mutations = array();
  $name_mutations[] = $name; // add full name, see https://github.com/Project60/org.project60.banking/issues/157
  for ($i=0; $i < count($name_bits); $i++) {
    if (strlen($name_bits[$i])>2) {
      $name_mutations[] = $name_bits[$i];
    }

    // EXTRACT sort_name format
    if (empty($params['sort_name_format'])) {
      $config = CRM_Core_Config::singleton();
      if (empty($config->sort_name_format)) {
        $sort_name_format = "{contact.last_name}{, }{contact.first_name}";
      } else {
        $sort_name_format = $config->sort_name_format;
      }
    } else {
      $sort_name_format = $params['sort_name_format'];
    }
    // replace stuff
    $sort_name_format = str_replace('{, }', ', ', $sort_name_format);
    $sort_name_format = str_replace('{ }', ' ', $sort_name_format);

    for ($j=$i+1; $j < count($name_bits); $j++) {
      $mutation = preg_replace('#\{[\w\.]+\}#', $name_bits[$i], $sort_name_format, 1);
      $name_mutations[] = preg_replace('#\{[\w\.]+\}#', $name_bits[$j], $mutation);

      $mutation = preg_replace('#\{[\w\.]+\}#', $name_bits[$j], $sort_name_format, 1);
      $name_mutations[] = preg_replace('#\{[\w\.]+\}#', $name_bits[$i], $mutation);
    }
  }


  // sort by length, so the longest combination is looked for first
  $name_mutations = array_unique($name_mutations);
  usort($name_mutations, function($a, $b) {
    return strlen($b) - strlen($a);
  }); // search first for the longest combination

  // respect the exact_match_wins flag:
  if (!empty($params['exact_match_wins'])) {
    // let's try first to get exact match(es) and skip the more advanced matching
    $contacts_found = _civicrm_api3_banking_lookup_contactbyname_exact($name_mutations);
    if (!empty($contacts_found)) {
      return $contacts_found;
    }
  }

  // run the actual search
  if (empty($params['mode']) || $params['mode']=='getquick') {
    $contacts_found = _civicrm_api3_banking_lookup_contactbyname_api($name_mutations, $params);
  } elseif ($params['mode']=='sql') {
    $contacts_found = _civicrm_api3_banking_lookup_contactbyname_sql($name_mutations, $params);
  } else { // OFF / invalid
    $contacts_found = array();
  }

  // apply penalties
  if (!empty($params['penalties'])) {
    _civicrm_api3_banking_lookup_contactbyname_penalties($contacts_found, $params['penalties']);
  }

  // sort by probability
  arsort($contacts_found);

  // apply contact_type filters, if given
  if (!empty($params['ignore_contact_types']) && !empty($contacts_found)) {
    // run a SQL filter query instead of loading every contact
    $all_ids = implode(',', array_keys($contacts_found));
    $ignore_types = '"'.implode('","', explode(',', $params['ignore_contact_types'])).'"';
    $filtered_ids_query = "SELECT id FROM civicrm_contact WHERE id IN ($all_ids) AND (contact_type IN ($ignore_types) OR contact_sub_type IN ($ignore_types));";
    $filtered_ids = CRM_Core_DAO::executeQuery($filtered_ids_query);
    while ($filtered_ids->fetch()) {
      unset($contacts_found[$filtered_ids->id]);
    }
  }

  // apply hard cap, if given
  if (!empty($params['hard_cap']) && is_numeric($params['hard_cap'])) {
    if (count($contacts_found) > (int)$params['hard_cap']) {
      $contacts_found = array_slice($contacts_found, 0, (int) $params['hard_cap'], true);
    }
  }

  // apply hard cap probability, if given
  if (!empty($params['hard_cap_probability']) && is_numeric($params['hard_cap_probability'])) {
    $hard_cap_probability = (float) $params['hard_cap_probability'];
    foreach ($contacts_found as $contact_id => $probability) {
      if ($probability < $hard_cap_probability) {
        unset($contacts_found[$contact_id]);
      }
    }
  }

  // apply soft cap, if given
  if (!empty($params['soft_cap_probability']) && is_numeric($params['soft_cap_probability'])) {
    $soft_cap_probability = (float) $params['soft_cap_probability'];
    $soft_cap_min = 0;
    if (!empty($params['soft_cap_min']) && is_numeric($params['soft_cap_min'])) {
      $soft_cap_min = (int) $params['soft_cap_min'];
    }

    $index = 0;
    foreach ($contacts_found as $contact_id => $probability) {
      $index += 1;
      if (($index > $soft_cap_min) && ($probability < $soft_cap_probability)) {
        unset($contacts_found[$contact_id]);
      }
    }
  }

  return civicrm_api3_create_success($contacts_found);
}

/**
 * Look for an exact match
 *
 * @author X+
 * @param $name_mutations array name mutations
 * @return array
 */
function _civicrm_api3_banking_lookup_contactbyname_exact ($name_mutations) {
  $contacts_found = array();
  $longest_mutation = strlen($name_mutations[0]);

  // compile SQL query
  $sql_clauses = array();
  foreach ($name_mutations as $name_mutation) {
    if (strlen($name_mutation) < $longest_mutation)
      return $contacts_found;
    $name_mutation = CRM_Utils_Type::escape($name_mutation, 'String');
    $search_query = "SELECT id, sort_name FROM civicrm_contact WHERE is_deleted=0 AND (`sort_name` = '{$name_mutation}');";
    // error_log($search_query);
    $search_results = CRM_Core_DAO::executeQuery($search_query);
    while ($search_results->fetch()) {
      $contacts_found[$search_results->id] = 1.0;
    }
  }
  return $contacts_found;
}

/**
 * find some contacts via SQL
 */
function _civicrm_api3_banking_lookup_contactbyname_sql($name_mutations, $params) {
  $contacts_found = array();
  $longest_mutation = 0;

  // compile SQL query
  $sql_clauses = array();
  foreach ($name_mutations as $name_mutation) {
    $name_mutation = CRM_Utils_Type::escape($name_mutation, 'String');
    $sql_clauses[] = "(`sort_name` LIKE '{$name_mutation}%')";
    $longest_mutation = max($longest_mutation, strlen($name_mutation));
  };

  $search_term = implode(' OR ', $sql_clauses);
  $search_query = "SELECT id, sort_name FROM civicrm_contact WHERE is_deleted=0 AND ({$search_term});";
  // error_log($search_query);
  $search_results = CRM_Core_DAO::executeQuery($search_query);
  while ($search_results->fetch()) {
    // evaluate each result
    $compare_name = strtolower($search_results->sort_name);
    $probability = 0.0;
    foreach ($name_mutations as $name_mutation) {
      if ($compare_name == $name_mutation) {
        $probability = 1.0;
      } else {
        // not a full match -> calculate similarity
        $similarity = 0.0; // value [0..100]
        similar_text(strtolower($name_mutation), $compare_name, $similarity);
        $probability = max($probability, $similarity / 100.0);
      }

      if ($probability == 1.0) {
        break;
      }
    }

    // deduct percent points for shorter matches
    $probability -= ($longest_mutation - strlen($name_mutation)) / 100.0;

    if ($probability > 0) {
      // square value for better distribution, multiply by 0.999 to avoid 100% match based on name
      $contacts_found[$search_results->id] = $probability * $probability * 0.999;
    }
  }

  return $contacts_found;
}

/**
 * find some contacts via API
 */
function _civicrm_api3_banking_lookup_contactbyname_api($name_mutations, $params) {
  $contacts_found = array();
  // query quicksearch for each combination
  foreach ($name_mutations as $name_mutation) {
    $result = civicrm_api3('Contact', 'getquick', array('name' => $name_mutation));
    foreach ($result['values'] as $contact) {
      // get the current maximum similarity...
      if (isset($contacts_found[$contact['id']])) {
        $probability = $contacts_found[$contact['id']];
      } else {
        $probability = 0.0;
      }

      // now, we'll have to find the maximum similarity with any of the name mutations
      $compare_name = strtolower($contact['sort_name']);
      foreach ($name_mutations as $name_mutation) {
        $new_probability = 0.0;
        similar_text(strtolower($name_mutation), $compare_name, $new_probability);
        //error_log("Compare '$name_mutation' to '".$contact['sort_name']."' => $new_probability");
        $new_probability /= 100.0;
        if ($new_probability > $probability) {
          // square value for better distribution, multiply by 0.999 to avoid 100% match based on name
          $probability = $new_probability * $new_probability * 0.999;
        }
      }

      $contacts_found[$contact['id']] = $probability;
    }
  }

  return $contacts_found;
}

/**
 * helper function for civicrm_api3_banking_lookup_contactbyname:
 * will apply penalties to the $contactID2probability list
 *
 * @param contactID2probability list of contact_ids with the currently assigned probability.
 *                               values will be adjusted by this method
 * @param penalty_specs         penalty specification from the matcher's configuration
 */
function _civicrm_api3_banking_lookup_contactbyname_penalties(&$contactID2probability, $penalty_specs) {
  // STEP 1: GATHER DATA
  $contact2relation = array();
  $contact2info = array();
  $relation_type_ids = array();
  foreach ($penalty_specs as $penalty) {
    if ($penalty->type == 'relation') {
      if ((int) $penalty->relation_type_id) {
        $relation_type_ids[] = (int) $penalty->relation_type_id;
      } else {
        CRM_Core_Error::debug_log_message("org.project60.banking.lookup - invalid or no 'relation_type_id' given in penalty definition.");
      }
    } elseif ($penalty->type == 'individual_single_name') {
      if (!empty($contactID2probability)) {
        $info_query = civicrm_api3('Contact', 'get', array(
            'id'           => array('IN' => array_keys($contactID2probability)),
            'return'       => 'first_name,last_name,contact_type',
            'sequential'   => 0,
            'option.limit' => 0));
        $contact2info = $info_query['values'];

      }
    }
  }

  if (!empty($relation_type_ids) && !empty($contactID2probability)) {
    // load all relationship data
    $contact_ids_string       = implode(',', array_keys($contactID2probability));
    $relation_type_ids_string = implode(',', $relation_type_ids);
    $sql = "SELECT contact_id_a, contact_id_b, relationship_type_id FROM civicrm_relationship WHERE contact_id_a IN ($contact_ids_string) and relationship_type_id IN ($relation_type_ids_string) AND is_active=1;";
    $query = CRM_Core_DAO::executeQuery($sql);
    while ($query->fetch()) {
      $contact2relation[$query->contact_id_a][] = $query->relationship_type_id;
    }
  }


  // STEP 2: apply penalties
  foreach ($contactID2probability as $contact_id => $probability) {
    foreach ($penalty_specs as $penalty) {
      $probability_penalty = (float) $penalty->penalty;

      if ($penalty->type == 'relation') {
        if (!empty($contact2relation[$contact_id]) && in_array($penalty->relation_type_id, $contact2relation[$contact_id])) {
          // penalty applies
          $probability = max(0.0, $probability - $probability_penalty);
        }

      } elseif ($penalty->type == 'individual_single_name') {
        // check if contact is an individual...
        $info = CRM_Utils_Array::value($contact_id, $contact2info, array());
        if (!empty($info['contact_type']) && $info['contact_type'] == 'Individual') {
          // ...and has only one name
          if (empty($info['first_name']) || empty($info['last_name'])) {
            // penalty applies
            $probability = max(0.0, $probability - $probability_penalty);
          }
        }

      } else {
        CRM_Core_Error::debug_log_message("org.project60.banking.lookup - penalty type not implemented: '{$penalty->type}'. Ignored.");
      }
    }
    $contactID2probability[$contact_id] = $probability;
  }
}
