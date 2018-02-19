<?php

/**
 * BankingRule.Getruledata API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_rule_Getruledata_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * BankingRule.Getruledata API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_rule_Getruledata($params) {
  $rule = CRM_Banking_Rules_Rule::get($params['id']);
  $data = $rule->getRuleData();

  // FIXME? There is probably a better way to do this. Björn?
  // In order that the editor can know what to set we need the config for this particular plugin.
  // That can't be hard-coded, so we have to look it up.
  $rules_analyser_plugin_id = civicrm_api3('OptionValue', 'getvalue', [
    'return'          => "id",
    'option_group_id' => "civicrm_banking.plugin_types",
    'name'            => "analyser_rules",
  ]);
  $result = civicrm_api3('BankingPluginInstance', 'getsingle', ['plugin_class_id' => $rules_analyser_plugin_id]);
  $config = json_decode($result['config'], TRUE);
  $data['plugin_config'] = $config;


  // Return the result.
  return civicrm_api3_create_success($data, $params, 'BankingRule', 'Getruledata');
}
