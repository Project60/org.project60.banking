<?php
use CRM_Banking_ExtensionUtil as E;

/**
 * BankingRule.Getsearchresults API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_rule_Getsearchresults_spec(&$spec) {
  foreach ([
    // Numbers:
      'amount_min'    => 'amount_min',
      'amount_max'    => 'amount_max',
      'created_by'    => 'Contact ID',
    // Substring matches:
      'name'          => 'Rule name (substring match)',
      'party_ba_ref'  => 'party_ba_ref',
      'ba_ref'        => 'ba_ref',
      'party_name'    => 'party_name',
      'tx_reference'  => 'tx_reference',
      'tx_purpose'    => 'tx_purpose',
    // Special:
      'is_enabled'    => 'If specified, limit by enabled (1) or disabled(0) rules, otherwise all are returned.',
      'last_match_min' => 'If specified, the last match date must be on or before this date',
      'last_match_max' => 'If specified, the last match date must be on or after this date',
      'match_counter_min' => 'If specified, the match count must be this number or more',
      'match_counter_max' => 'If specified, the match count must be this number or less',
      'execution'     => 'JSON object to match execution key/value pairs, e.g. { contact_id: 123, membership_id: 456 } would find rules that included setting these parameters.',
      'conditions'    => 'JSON object to match conditions key/value pairs, e.g. { foo: "bar" } would find rules that matched custom condition "foo" with "bar" as a substring.',
    // Other?
      'type'          => 'type', //???
      'valid_until'   => 'valid_until', // could be nicer.
    ] as $k=>$v) {
    $spec[$k]['description'] = $v;
  }
}

/**
 * BankingRule.Getsearchresults API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_rule_Getsearchresults($params) {

  $sql = [];
  $sql_params = [];
  $c = 1;

  // Numbers.
  foreach ([ 'amount_min', 'amount_max'] as $_) {
    if (isset($params[$_])) {
      if ($params[$_]) {
        // we have a value.
        $sql_params[$c] = [$params[$_], 'Money'];
        $sql[] = "$_ = %" . ($c++);
      }
      else {
        // No value, but we have the key, so we test for NULL.
        $sql[] = "$_ IS NULL";
      }
    }
  }
  // Integers
  if (!empty($params['created_by'])) {
    $sql_params[$c] = [$params['created_by'], 'String'];
    $sql[] = "created_by = %" . ($c++);
  }
  foreach ([ 'name', 'party_ba_ref', 'ba_ref', 'party_name', 'tx_reference', 'tx_purpose' ] as $_) {

    if (isset($params[$_])) {
      if ($params[$_]) {
        // we have a value.
        $sql_params[$c] = [$params[$_], 'String', CRM_Core_DAO::QUERY_FORMAT_WILDCARD];
        $sql[] = "$_ LIKE %" . ($c++);
      }
      else {
        // No value, but we have the key, so we test for NULL.
        $sql[] = "$_ IS NULL";
      }
    }
  }
  if (isset($params['is_enabled'])) {
    $sql[] = "is_enabled = " . ( $params['is_enabled'] ? '1' : '0');
  }

  if (!empty($params['last_match_min'])) {
    $sql_params[$c] = [date('YmdHis', strtotime($params['last_match_min'])), 'Timestamp'];
    $sql[] = 'last_match >= %' . ($c++);
  }
  if (!empty($params['last_match_max'])) {
    $sql_params[$c] = [date('YmdHis', strtotime($params['last_match_max'])), 'Timestamp'];
    $sql[] = 'last_match <= %' . ($c++);
  }
  if (!empty($params['match_counter_min'])) {
    $sql_params[$c] = [$params['match_counter_min'], 'Positive'];
    $sql[] = 'match_counter >= %' . ($c++);
  }
  if (!empty($params['match_counter_max'])) {
    $sql_params[$c] = [$params['match_counter_max'], 'Positive'];
    $sql[] = 'match_counter <= %' . ($c++);
  }

  $where = $sql ? 'WHERE ' . implode(' AND ', $sql) : '';

  // parse sort.
  $sort = '';
  if (!empty($params['options']['sort'])
    && preg_match('/^(last_match|match_counter) (DE|A)SC$/', $params['options']['sort']  )) {

    $sort = 'ORDER BY ' . $params['options']['sort'];
  }

  // Get results from SQL.
  $sql = "SELECT * FROM civicrm_bank_rules $where $sort";
  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);


  //
  // Now loop and test conditions and execution criteria.
  //
  if (empty($params['conditions'])) {
    $conditions = [];
  }
  else {
    // Minimal parsing of conditions.
    if (!is_array($params['conditions'])) {
      throw new API_Exception("Expect conditions parameter to be an object.");
    }
    $conditions = $params['conditions'];
  }
  if (empty($params['execution'])) {
    $executions = [];
  }
  else {
    // Minimal parsing of execution.
    if (!is_array($params['execution'])) {
      throw new API_Exception("Expect execution parameter to be an object.");
    }
    $executions = $params['execution'];
  }

  // Unpack data and run other tests on conditions, execution.
  $found = 0;
  // Default to returning 10 rules at a time.
  $offset = (empty($params['options']['offset']) ? 0 : (int)$params['options']['offset']);
  $limit  = (empty($params['options']['limit']) ? 10 : (int)$params['options']['limit']);
  $results = [];
  while ($dao->fetch()) {

    // Load the rule object.
    $obj = new CRM_Banking_Rules_Rule();
    $obj->setFromDao($dao);

    $rule_conditions = $obj->getConditions();
    foreach ($conditions as $condition => $value) {
      if (!isset($rule_conditions[$condition]['full_match']) || mb_stripos($rule_conditions[$condition]['full_match'], $value) === FALSE) {
        // Don't match any further criteria and skip this rule.
        continue 2;
      }
    }
    $rule_executions = $obj->getExecution();
    foreach ($executions as $execution => $value) {

      // Is there a match for this execution in any of the executions?
      $execution_match = FALSE;
      foreach ($rule_executions as $rule_execution) {
        if ($rule_execution['set_param_name'] == $execution && $rule_execution['set_param_value'] == $value) {
          $execution_match = TRUE;
          break;
        }
      }
      if (!$execution_match) {
        // Don't match any further criteria and skip this rule.
        continue 2;
      }
    }

    $found++;
    if ($found > $offset && $found <= $offset+$limit) {
      $results[] = $obj->getRuleData();
    }
  }

  /* We cannot use SQL's limit.
  // Default to returning 10 rules at a time.
  $limit = [
    (empty($params['options']['offset']) ? 0 : (int)$params['options']['offset']),
    (empty($params['options']['limit']) ? 10 : (int)$params['options']['limit']),
  ];
  if ($limit !== [0, 0]) {
    $limit_sql = "LIMIT $limit[0], $limit[1]";
  }
  else {
    $limit_sql = '';
  }

  // Count.
  $sql = "SELECT COUNT(*) FROM civicrm_bank_rules $where";
  $count = CRM_Core_DAO::singleValueQuery($sql, $sql_params);
   */

  $results = [
    'sql'         => $where,
    'total_count' => $found,
    'offset'      => $offset,
    'limit'       => $limit,
    'rules'       => $results,
  ];
  $dao->free();
  return $results;

  // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
  //return civicrm_api3_create_success($results, $params, 'BankingRule', 'GetSearchResults');
  //throw new API_Exception();
}
