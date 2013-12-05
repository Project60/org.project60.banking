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


-- Create instances (examples)
SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='importer_csv'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_importer, @plugin_class, 'StarMoney CSV', 'Importiert StarMoney CSV Kontoauszüge', 1, 100, '{"delimiter":";","encoding":"CP1252","header":1,"title":"DB {starting_date} - {ending_date} [{md5}]","bank_reference":"DB-StarMoney-{txn_id}","defaults":{},"rules":[{"from":"Kontonummer","to":"_ba_id","type":"set"},{"from":"Bankleitzahl","to":"_bank_id","type":"set"},{"from":"Betrag","to":"amount","type":"amount"},{"from":"Betrag - Währung","to":"currency","type":"set"},{"from":"Buchungstext","to":"transaction_class","type":"set"},{"from":"Buchungstag","to":"booking_date","type":"strtotime:d.m.Y"},{"from":"Begünstigter/Absender - Bankleitzahl","to":"_party_bank_id","type":"set"},{"from":"Begünstigter/Absender - Kontonummer","to":"_party_ba_id","type":"format:%010d"},{"from":"Begünstigter/Absender - Bankleitzahl","to":"NBAN_DE","type":"set"},{"from":"_party_ba_id","to":"NBAN_DE","type":"append:/"},{"from":"Begünstigter/Absender - Name","to":"name","type":"set"},{"from":"Laufende Nummer","to":"txn_id","type":"set"},{"from":"Primanota","to":"primanota","type":"set"},{"from":"Wertstellungstag","to":"value_date","type":"strtotime:d.m.Y"},{"from":"Verwendungszweckzeile 1","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 2","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 3","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 4","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 5","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 6","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 7","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 8","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 9","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 10","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 11","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 12","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 13","to":"purpose","type":"append:"},{"from":"Verwendungszweckzeile 14","to":"purpose","type":"append:"},{"from":"purpose","to":"purpose","type":"trim"}]}', '{}')

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_default'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Standardoptionen', 'Stellt die Standardoptionen bereit', 1, 10, '{"generate":1,"auto_exec":false,"manual_enabled":true,"manual_probability":"50%","manual_show_always":true,"manual_title":"Manuell verarbeitet","manual_message":"Wählen Sei diese Option <strong>nachdem</strong> Sie die Daten manuell im System verbucht haben.","manual_contribution":"Bitte tragen Sie hier die Zuwendungs-ID ein, falls eine erzeugt wurde: ","manual_default_source":"Offline","manual_default_financial_type_id":1,"ignore_enabled":true,"ignore_show_always":true,"ignore_probability":"0.1","ignore_title":"Gehört nicht in CiviCRM","ignore_message":"Diese Buchung hat nichts mit CiviCRM zu tun."}', '{}')

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_ignore'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Ignorieren', 'Erkennt irrelevante Zahlungen und filtert sie heraus.', 1, 100, '{"generate":1,"auto_exec":true,"ignore":[{"field":"transaction_class","regex":"#^KONTOABRECHNUNG$#","message":"Dies sind Kontoführungsgebühren"},{"field":"purpose","regex":"#^.+MONATLICHES NUTZUNGSENTGELTFUR DEN ELECTRONIC BANKINGZUGANG.+$#","message":"Dies sind Kontokosten"},{"field":"transaction_class","regex":"#^ZINSEN/KOSTEN/AUSLAGEN$#","message":"Dies sind Kontoführungsgebühren","precision":1.0}]}', '{}')

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_contribution'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Zuwendungen', 'Sucht relevante bestehende Zuwendungen', 1, 50, '{"generate":1,"auto_exec":false,"threshold":0.01}', '{}')

SELECT @plugin_class := civicrm_option_value.id FROM civicrm_option_value, civicrm_option_group 
  WHERE civicrm_option_value.option_group_id = civicrm_option_group.id 
    AND civicrm_option_value.name='matcher_batch'
    AND civicrm_option_group.name='civicrm_banking.plugin_types';
INSERT INTO civicrm_bank_plugin_instance (`plugin_type_id`, `plugin_class_id`, `name`, `description`, `enabled`, `weight`, `config`, `state`)
  VALUES (@type_matcher, @plugin_class, 'Zuwendungen', 'Sucht relevante bestehende Zuwendungen', 1, 50, '{"generate":1,"auto_exec":false,"export_date_to_payment_min":"+0 days","export_date_to_payment_max":"+30 days","export_date_to_payment_delay":"+3 days","export_date_to_payment_tolerance":"2 days","total_amount_tolerance":0.10,"exclude_batches_older_than":"1 year"}', '{}')
