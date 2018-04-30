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
  $results = CRM_Banking_Rules_Rule::search($params);
  // Convert rule objects to data arrays.
  $results['rules'] = array_map(function($rule) { return $rule->getRuleData(); }, $results['rules']);
  return $results;
}
