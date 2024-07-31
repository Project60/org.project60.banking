<?php
use CRM_Banking_ExtensionUtil as E;

/**
 * BankingPluginInstance.import API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_banking_plugin_instance_import_spec(&$spec) {
  $spec['plugin_id'] = [
    'name'         => 'plugin_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Banking Plugin ID',
  ];
  $spec['file_path'] = [
    'name'         => 'file_path',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'File Path',
    'description'  => 'Path to file that should be imported'
  ];
  $spec['dry_run'] = [
    'name'         => 'dry_run',
    'api.default'  => FALSE,
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'title'        => 'Dry Run?',
    'description'  => 'Perform a dry run of the import?'
  ];
}

/**
 * BankingPluginInstance.import API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_banking_plugin_instance_import($params) {
  // Security analysis: This API accepts arbitrary file paths and could (indirectly)
  // leak their content e.g. through logs or specially-crafted import plugins.
  // To avoid scenarios in which untrusted calls use this API, we reject all requests
  // with check_permissions != 0. This is roughly the same security barrier
  // implemented for options.move-file in the Attachment.create API3
  if (!empty($params['check_permissions'])) {
    throw new API_Exception('API only supported on secure calls');
  }
  $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');
  /**
   * @var CRM_Banking_PluginModel_Importer $plugin_instance
   */
  $plugin_instance = NULL;
  foreach ($plugin_list as $plugin) {
    if ($plugin->id == $params['plugin_id']) {
      $plugin_instance = $plugin->getInstance();
    }
  }
  if (is_null($plugin_instance)) {
    throw new API_Exception('Unknown plugin id ' . $params['plugin_id']);
  }
  if (!$plugin_instance::does_import_files()) {
    throw new API_Exception('Plugin does not support import files');
  }
  if (!is_readable($params['file_path'])) {
    throw new API_Exception('file_path is not readable');
  }
  $import_parameters = [
    'dry_run' => !empty($params['dry_run']) ? 'on' : 'off',
    'source'  => basename($params['file_path']),
  ];
  $plugin_instance->resetImporter();
  if ($plugin_instance->probe_file($params['file_path'], $import_parameters)) {
    $plugin_instance->import_file($params['file_path'], $import_parameters);
  }
  else {
    throw new API_Exception('File rejected by importer!');
  }

  $warnings = [];
  $errors = [];
  foreach ($plugin_instance->getLog() as $log_entry) {
    if ($log_entry[3] == CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN) {
      $warnings[] = $log_entry[2];
    } elseif ($log_entry[3] == CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR) {
      $errors[] = $log_entry[2];
    }
  }

  return civicrm_api3_create_success([
    'warnings' => $warnings,
    'errors' => $errors,
  ]);
}
