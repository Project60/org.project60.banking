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
  // @todo
}
