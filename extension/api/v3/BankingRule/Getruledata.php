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
  $spec['id']['description'] = 'Rule ID. If missing just returns the config.';
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

  if (!empty($params['id'])) {
    $rule = CRM_Banking_Rules_Rule::get($params['id']);
    $data = $rule->getRuleData();
  }
  else {
    $data = [];
  }

  // In order that the editor can know what to set we need the config for this particular plugin.
  $rules_analyser_plugin_id = civicrm_api3('OptionValue', 'getvalue', [
    'return'          => "id",
    'option_group_id' => "civicrm_banking.plugin_types",
    'name'            => "analyser_rules",
  ]);
  // load the [first, hopefully only] Matcher of this plugin class type and get its config.
  $pi_bao = new CRM_Banking_BAO_PluginInstance();
  $pi_bao->get('plugin_class_id', $rules_analyser_plugin_id);
  $pi = $pi_bao->getInstance();
  $pi_config = $pi->getConfig();
  $data['plugin_config'] = json_decode(json_encode($pi_config), TRUE);

  // Return the result.
  return civicrm_api3_create_success($data, $params, 'BankingRule', 'Getruledata');
}
