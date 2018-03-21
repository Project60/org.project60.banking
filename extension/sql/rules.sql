-- +--------------------------------------------------------+
-- | Project 60 - CiviBanking                               |
-- | Copyright (C) 2018 SYSTOPIA                            |
-- | Author: B. Endres (endres -at- systopia.de)            |
-- |         R. Lott (hello -at- artfulrobot.uk)            |
-- | http://www.systopia.de/                                |
-- +--------------------------------------------------------+
-- | This program is released as free software under the    |
-- | Affero GPL v3 license. You can redistribute it and/or  |
-- | modify it under the terms of this license which you    |
-- | can read by viewing the included agpl.txt or online    |
-- | at www.gnu.org/licenses/agpl.html. Removal of this     |
-- | copyright header is strictly prohibited without        |
-- | written permission from the original author(s).        |
-- +--------------------------------------------------------+

-- These structures will provide the data for the
--  CRM_Banking_PluginImpl_Matcher_Rules matcher

CREATE TABLE IF NOT EXISTS `civicrm_bank_rules` (
-- matching rule id
     `id`            int unsigned  NOT NULL AUTO_INCREMENT,

-- matching rule identifiers:
     `amount_min`   decimal(20,2)              COMMENT 'minimum amount',
     `amount_max`   decimal(20,2)              COMMENT 'maximum amount',
     `party_ba_ref`   varchar(64)              COMMENT 'transaction party\'s bank account reference',
     `ba_ref`         varchar(64)              COMMENT 'organisations\'s bank account reference',
     `party_name`    varchar(128)              COMMENT 'transaction party\'s name',
     `tx_reference`  varchar(128)              COMMENT 'transaction reference',
     `tx_purpose`    varchar(128)              COMMENT 'transaction purpose',

-- matching execution
     `conditions`           text               COMMENT 'an (extra) set of conditions to check',
     `execution`            text               COMMENT 'execution instructions',

-- rule metadata:
     `name`           varchar(64)              COMMENT 'optional rule name',
     `type`               tinyint NOT NULL     COMMENT '1 = analyser, 2 = matcher type',
     `is_enabled`         tinyint NOT NULL     COMMENT 'set to 1 to enable the rule',
     `valid_until`       datetime              COMMENT 'this rule should not match after this date',
     `created_by`    int unsigned              COMMENT 'contact who created this rule',
     `match_counter` int unsigned DEFAULT 0    COMMENT 'number of matches',
     `last_match`        datetime DEFAULT NULL COMMENT 'last time this rule matched',
     PRIMARY KEY          (`id`),
     INDEX `amount_min`   (`amount_min`),
     INDEX `amount_max`   (`amount_max`),
     INDEX `party_ba_ref` (`party_ba_ref`),
     INDEX `ba_ref`       (`ba_ref`),
     INDEX `party_name`   (`party_name`),
     INDEX `tx_reference` (`tx_reference`),
     INDEX `tx_purpose`   (`tx_purpose`),
     INDEX `is_enabled`   (`is_enabled`),
     INDEX `type`         (`type`),
     CONSTRAINT FK_civicrm_bank_rules_created_by FOREIGN KEY (`created_by`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
