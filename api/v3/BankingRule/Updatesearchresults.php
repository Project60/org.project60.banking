<?php
use CRM_Banking_ExtensionUtil as E;

/**
 * BankingRule.Updatesearchresults API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_rule_Updatesearchresults_spec(&$spec) {
  include_once( __DIR__ . '/Getsearchresults.php');
  _civicrm_api3_banking_rule_Getsearchresults_spec($spec);

  $spec['update'] = [
    'api.required' => 1,
    'description' => 'JSON of field:value pairs to update, e.g. { is_enabled: 1 }',
  ];
}

/**
 * BankingRule.Updatesearchresults API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_rule_Updatesearchresults($params) {

  // Fetch all results for this query.
  $all_results_params = $params;
  unset($all_results_params['update']);
  $all_results_params['options']['limit'] = 0;
  $all_results_params['options']['offset'] = 0;
  $results = CRM_Banking_Rules_Rule::search($all_results_params);

  // Update them.
  // Currently only is_enabled is the only update we allow, but this could be
  // extended to allow other bulk updates.
  if (isset($params['update']['is_enabled'])
    && in_array($params['update']['is_enabled'], [0, 1])) {

    $enabled = (int) $params['update']['is_enabled'];
    foreach ($results['rules'] as $rule) {
      $rule->setIs_enabled($enabled);
      $rule->save();
    }
  }

  // Extract the page we need to display.
  $offset = (empty($params['options']['offset']) ? 0 : (int)$params['options']['offset']);
  $limit  = (empty($params['options']['limit']) ? 10 : (int)$params['options']['limit']);

  $return = [];
  foreach ($results['rules'] as $i=>$rule) {
    if ($i >= $offset && $i < $offset+$limit) {
      $return[] = $rule->getRuleData();
    }
  }

  $results['limit'] = $limit;
  $results['offset'] = $offset;
  $results['rules'] = $return;

  return $results;
}
