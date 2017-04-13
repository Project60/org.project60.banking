<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * This PostProcessor update the contact's address with the one from the bank statement
 */
class CRM_Banking_PluginImpl_PostProcessor_AddressUpdate extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    // if (!isset($config->threshold)) $config->threshold = 0.5;
    // if (!isset($config->received_date_minimum)) $config->received_date_minimum = "-10 days";

  }

  /**
   * Postprocess the (already executed) match
   *
   * @param $match    the executed match
   * @param $btx      the related transaction
   * @param $context  the matcher context contains cache data and context information
   *
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_PluginModel_Matcher $matcher) {
    // this matcher only makes sense for individuals
    $contact_id = $this->getSoleContactID($match, $btx, $context);
    if ($contact_id && $this->shouldExecute($match, $btx, $context)) {

      // see if the address is complete

      // calculate the differences, score

      // decide what to do

      // do it
    }
  }
}

