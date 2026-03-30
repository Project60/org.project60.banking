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

declare(strict_types = 1);

use Civi\Api4\OptionGroup;
use CRM_Banking_ExtensionUtil as E;

/**
 * Install/Update the option values
 *
 * @deprecated Use managed entities instead.
 */
function banking_civicrm_install_options($data) {
  foreach ($data as $groupName => $group) {
    // check group existence
    $result = OptionGroup::get(FALSE)->addWhere('name', '=', $groupName)->execute()->first();
    if (NULL === $result) {
      $params = [
        'name' => $groupName,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'title' => $group['title'],
        'description' => $group['description'],
      ];
      $result = OptionGroup::create(FALSE)->setValues($params)->execute()->single();
    }
    $group_id = $result['id'];

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 10;
      foreach ($groupValues as $valueName => $value) {
        // find option value
        $result = civicrm_api3('OptionValue', 'get', [
          'name'            => $valueName,
          'option_group_id' => $group_id,
        ]);
        if (count($result['values']) == 0) {
          // create a new entry
          $params = [];
          $params['option_group_id'] = $group_id;
          $params['name']            = $valueName;
          $params['is_active']       = 1;
          $params['weight']          = $weight;
          $weight += 10;
        }
        else {
          // update existing entry
          // update
          $params = reset($result['values']);
        }

        $fields = ['label', 'value', 'description', 'is_default', 'is_reserved'];
        foreach ($fields as $field) {
          if (isset($value[$field])) {
            $params[$field] = $value[$field];
          }
        }
        $result = civicrm_api3('option_value', 'create', $params);
      }
    }
  }
}
