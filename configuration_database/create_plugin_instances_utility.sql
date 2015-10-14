-- save mode & disable backslashes (if you upload JSON files with escaped values via console)
SET @@sql_mode=CONCAT_WS(',', @@sql_mode, 'NO_BACKSLASH_ESCAPES');

-- See types
SELECT id, name, description, enabled, weight, plugin_type_id, plugin_class_id FROM civicrm_bank_plugin_instance WHERE enabled=1 order by weight;
UPDATE civicrm_bank_plugin_instance SET config='' WHERE id=;

-- Gather the types
SELECT @type_importer := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.value=1
    AND civicrm_option_group.name='civicrm_banking.plugin_classes';

SELECT @type_matcher := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.value=2
    AND civicrm_option_group.name='civicrm_banking.plugin_classes';

SELECT @type_exporter := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.value=3
    AND civicrm_option_group.name='civicrm_banking.plugin_classes';


-- Create importers
SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='importer_csv'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_importer, @plugin_class, 'Santander CSV', 'Imports transactions from Detailed Santander CSV file', 1, 100, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='importer_csv'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_importer, @plugin_class, 'PayPal CSV', 'Imports transactions from PayPal CSV files', 1, 100, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='importer_csv'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_importer, @plugin_class, 'WorldPay CSV', 'Imports transactions from WorldPay CSV files', 1, 100, '{}', '{}');


-- Create analysers
SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='analyser_regex'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'General Analyser', 'Parses addresses and similar stuff', 1, 40, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='analyser_regex'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Fingerprinting', 'Create fingerprints from the reference field', 1, 45, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='analyser_account'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Fingerprinting Lookup', 'Lookup contacts via the fingerprint', 1, 47, '{}', '{}');


-- Create matchers
SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_ignore'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Ignore Filter', 'This payment can be safely ignored', 1, 30, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_membership'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Find Membership', 'Connects payments with memberships', 1, 50, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_recurring'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Recurring Contribution', 'Creates recurring contribution installments.', 1, 55, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_create'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Create Donation', 'Will create general contributions', 1, 60, '{}', '{}');

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_default'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Default Options', 'Provides the user with some default processing options.', 1, 100, '{}', '{}');
