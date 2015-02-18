
-- /*************************************************
--  *      Update skript for CiviBanking 0.5        *
--  *************************************************/

-- moved importer implementations to subfolder
SELECT @option_group_id := id FROM civicrm_option_group WHERE name = 'civicrm_banking.plugin_types';
UPDATE civicrm_option_value SET value='CRM_Banking_PluginImpl_Importer_CSV' WHERE option_group_id=@option_group_id AND value='CRM_Banking_PluginImpl_CSVImporter';
UPDATE civicrm_option_value SET value='CRM_Banking_PluginImpl_Importer_XML' WHERE option_group_id=@option_group_id AND value='CRM_Banking_PluginImpl_XMLImporter';

