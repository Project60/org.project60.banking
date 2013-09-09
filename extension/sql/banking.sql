-- /*******************************************************
-- *
-- * civicrm_bank_tx_batch
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_bank_tx_batch` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `issue_date` datetime NOT NULL   COMMENT 'When the statement was issued',
     `reference` varchar(64) NOT NULL   COMMENT 'The unique reference for this statement',
     `sequence` int NOT NULL   COMMENT 'Used to maintain ordering and consistency',
     `starting_balance` decimal(20,2)    ,
     `ending_balance` decimal(20,2)    ,
     `currency` varchar(3)    COMMENT 'Currency',
     `tx_count` int NOT NULL   ,
     `starting_date` datetime    COMMENT 'Start date of the statement period',
     `ending_date` datetime    COMMENT 'End date of the statement period' 
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `reference`(
        reference
  )
  
 
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

-- /*******************************************************
-- *
-- * civicrm_bank_account
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_bank_account` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `description` varchar(255)    COMMENT 'Purpose or use of the bank account',
     `created_date` datetime NOT NULL   ,
     `modified_date` datetime NOT NULL   ,
     `data_raw` text    COMMENT 'The complete information received for this bank account',
     `data_parsed` text    COMMENT 'A JSON-formatted array containing decoded fields',
     `contact_id` int unsigned    COMMENT 'FK to contact owning this account' 
,
    PRIMARY KEY ( `id` )
 
 
,          CONSTRAINT FK_civicrm_bank_account_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

-- /*******************************************************
-- *
-- * civicrm_bank_account_reference
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_bank_account_reference` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference` varchar(255)    COMMENT 'The value for this account',
     `reference_type_id` int unsigned NOT NULL   COMMENT 'Link to an option list',
     `ba_id` int unsigned    COMMENT 'FK to bank_account of target account' 
,
    PRIMARY KEY ( `id` )
 
    ,     INDEX `reftype`(
        ba_id
      , reference_type_id
  )
  
,          CONSTRAINT FK_civicrm_bank_account_reference_ba_id FOREIGN KEY (`ba_id`) REFERENCES `civicrm_bank_account`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

-- /*******************************************************
-- *
-- * civicrm_bank_tx
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_bank_tx` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `bank_reference` varchar(64) NOT NULL   COMMENT 'The unique reference for this transaction',
     `value_date` datetime NOT NULL   COMMENT 'Value date for this bank transaction',
     `booking_date` datetime NOT NULL   COMMENT 'Booking date for this bank transaction',
     `amount` decimal(20,2) NOT NULL   COMMENT 'Transaction amount (positive or negative)',
     `currency` varchar(3)    COMMENT 'Currency for the amount of the transaction',
     `type_id` int unsigned NOT NULL   COMMENT 'Link to an option list',
     `status_id` int unsigned NOT NULL   COMMENT 'Link to an option list',
     `data_raw` text    COMMENT 'The complete information received for this transaction',
     `data_parsed` text    COMMENT 'A JSON-formatted array containing decoded fields',
     `ba_id` int unsigned    COMMENT 'FK to bank_account of target account',
     `party_ba_id` int unsigned    COMMENT 'FK to bank_account of party account',
     `tx_batch_id` int unsigned    COMMENT 'FK to parent bank_tx_batch',
     `sequence` int unsigned    COMMENT 'Numbering local to the tx_batch_id',
     `suggestions` text    COMMENT 'A JSON-formatted array containing suggestions' 
,
    PRIMARY KEY ( `id` )
 
    ,     UNIQUE INDEX `bank_reference`(
        bank_reference
  )
  
,          CONSTRAINT FK_civicrm_bank_tx_ba_id FOREIGN KEY (`ba_id`) REFERENCES `civicrm_bank_account`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_bank_tx_party_ba_id FOREIGN KEY (`party_ba_id`) REFERENCES `civicrm_bank_account`(`id`) ON DELETE SET NULL,          CONSTRAINT FK_civicrm_bank_tx_tx_batch_id FOREIGN KEY (`tx_batch_id`) REFERENCES `civicrm_bank_tx_batch`(`id`) ON DELETE SET NULL  
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;



-- /*******************************************************
-- *
-- * civicrm_bank_plugin_instance
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_bank_plugin_instance` (


     `id` INT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `plugin_type_id` INT UNSIGNED NOT NULL   COMMENT 'Link to an option list of plugin types',
     `plugin_class_id` INT UNSIGNED NOT NULL   COMMENT 'Link to an option list of plugin class names',
     `name` VARCHAR(255)    COMMENT 'Name of the plugin',
     `description` TEXT    COMMENT 'Short description of what the plugin does',
     `enabled` TINYINT NOT NULL  DEFAULT 1 COMMENT 'If this plugin is enabled',
     `weight` DOUBLE NOT NULL  DEFAULT 100.0 COMMENT 'Relative weight of this plugin',
     `config` TEXT    COMMENT 'Configuration JSON',
     `state` TEXT    COMMENT 'State JSON' 
,
    PRIMARY KEY ( `id` )
 
 
 
)  ENGINE=INNODB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;
