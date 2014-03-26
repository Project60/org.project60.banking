<?php
// $Id$

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
 * File for the CiviCRM APIv3 banking lookup services
 *
 * @package CiviBanking Extension
 */


/**
 * Will provide a name based lookup for contacts. It is designed to take care of
 *  'tainted' string, i.e. containing abbreviations, initals, titles, etc.
 * 
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
  } else {
    $name = strtolower($params['name']);
    $contacts_found = array();
  }

  // get modifiers
  if (!isset($params['modifiers']) || empty($params['modifiers'])) {
    $modifiers = array();
  } else {
    $modifiers = json_decode($params['modifiers'], true);
  }

  // apply 'entire'-modifiers to string
  foreach ($modifiers as $modifier) {
    if (!empty($modifier['search']) && !empty($modifier['replace']) && (empty($modifier['mode']) || $modifier['mode']='entire') ) {
      $name = preg_replace($modifier['search'], $modifier['replace'], $name);
    }
  }

  // chop up the name string
  $name_bits = preg_split("( |,|&|\.)", $name, 0, PREG_SPLIT_NO_EMPTY);

  // apply 'alternative'-modifiers to string
  foreach ($name_bits as $name_bit) {
    foreach ($modifiers as $modifier) {
      if (!empty($modifier['search']) && !empty($modifier['replace']) && !empty($modifier['mode']) && $modifier['mode']='alternative' ) {
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
  for ($i=0; $i < count($name_bits); $i++) {
    if (strlen($name_bits[$i])>2) {
      $name_mutations[] = $name_bits[$i];
    }

    for ($j=$i+1; $j < count($name_bits); $j++) {
      $name_mutations[] = $name_bits[$i].', '.$name_bits[$j];
      $name_mutations[] = $name_bits[$j].', '.$name_bits[$i];
    }
  }

  // query quicksearch for each combination
  foreach ($name_mutations as $name_mutation) {
    $result = civicrm_api('Contact', 'getquick', array('name' => $name_mutation, 'version' => 3));
    if ($result['is_error']) {
      // that didn't go well...
      return civicrm_api3_create_error($result['error_message']);
    } 

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

  // sort by probability
  arsort($contacts_found);

  // apply contact_type filters, if given
  if (!empty($params['ignore_contact_types'])) {
    // run a SQL filter query instead of loading every contact
    $all_ids = implode(',', array_keys($contacts_found));
    $ignore_types = '"'.implode('","', split(',', $params['ignore_contact_types'])).'"';
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
