<?php

/**
 * BankingRule.Match API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_rule_Match_spec(&$spec) {
  $spec['matcher_id'] = [
    'description' => 'ID of the matcher plugin instance',
    'api.required' => 1,
  ];
  $spec['btx_id'] = [
    'description' => 'ID of the bank transaction to test against',
    'api.required' => 1,
  ];
}

/**
 * BankingRule.Match API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_rule_Match($params) {

  try {
    // Load the bank transaction.
    $btx = CRM_Banking_BAO_BankTransaction::findById($params['btx_id']);

    // load the Matcher and the mapping
    $pi_bao = new CRM_Banking_BAO_PluginInstance();
    $pi_bao->get('id', $params['matcher_id']);
    $pi = $pi_bao->getInstance();
    $pi_config = $pi->getConfig();
    if (isset($pi_config->field_mapping)) {
      $pi_mapping = $pi_config->field_mapping;
    } else {
      $pi_mapping = array();
    }

    // Create a disabled rule.
    $rule_data = $params;
    $rule_data['is_enabled'] = 0;
    // Remove data not required to create a rule.
    unset($rule_data['btx_id']);

    // Create the rule.
    $rule = CRM_Banking_PluginImpl_Matcher_RulesAnalyser::createRule($rule_data);

    // Got rule, now test our btx.
    $matches = CRM_Banking_Rules_Match::matchTransaction($btx, $pi_mapping, [], 1, 0, $rule->getId());

    // Now delete the rule we created.
    $rule->delete();

    // Return the result.
    return civicrm_api3_create_success(['match' => (count($matches) == 1)], $params, 'BankingRule', 'Match');
  }
  catch (Exception $e) {
    // If we have a rule we need to delete it now.
    if (isset($rule) && $rule->getId()) {
      $rule->delete();
    }

    throw new API_Exception($e->getMessage());
  }
}
