<?php

/**
 * BankingRule.Update API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_rule_Update_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * BankingRule.Update API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_rule_Update($params) {
  // todo
  $x = 1;
  try {
    $rule = CRM_Banking_Rules_Rule::get($params['id']);
    $_ = array_intersect_key($params, array_flip([
      'amount_min',
      'amount_max',
      'party_ba_ref',
      'ba_ref',
      'party_name',
      'tx_reference',
      'tx_purpose',
      'conditions',
      'execution',
      'name',
      'type',
      'is_enabled',
      'valid_until',
      'created_by',
      'match_counter',
      'last_match',
    ]));
    $rule->setFromArray($_, FALSE);
    $rule->save();

    $result = civicrm_api3('BankingRule', 'getRuleData', ['id' => $params['id']]);
    // Return the result.
    return civicrm_api3_create_success($result, $params, 'BankingRule', 'Update');
  }
  catch (Exception $e) {
    throw new API_Exception($e->getMessage());
  }

}
