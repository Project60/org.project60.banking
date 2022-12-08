<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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

use CRM_Banking_ExtensionUtil as E;

/**
 * This PostProcessor will mark the matched contact as 'deceased'
 */
class CRM_Banking_PluginImpl_PostProcessor_ContactDeceased extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->set_deceased_date))            $config->set_deceased_date            = 'btx.booking_date';
    if (!isset($config->tag_contact))                  $config->tag_contact                  = array();
    if (!isset($config->contribution_fields_required)) $config->contribution_fields_required = 'contact_id';
  }

  /**
   * Postprocess the (already executed) match
   *
   * @param $match    the executed match
   * @param $btx      the related transaction
   * @param $context  the matcher context contains cache data and context information
   *
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    if ($this->shouldExecute($match, $matcher, $context)) {
      // first: identify contact(s)
      $contact_id = NULL;
      $contributions = $this->getContributions($context);
      foreach ($contributions as $contribution) {
        if ($contact_id == NULL) {
          $contact_id = $contribution['contact_id'];
        } elseif ($contact_id != $contribution['contact_id']) {
          // there are multiple contacts connected to this match
          $this->logMessage("Multiple contacts connected to this match, cannot proceed", 'error');
          return;
        }
      }

      // if we have a contact:
      if ($contact_id) {
        $contact_lookup = civicrm_api3('Contact', 'get', array(
          'id'     => $contact_id,
          'return' => 'is_deceased,is_deleted,deceased_date,id',
          ));
        if ($contact_lookup['id']) {

          // mark contact as deceased
          $contact = reset($contact_lookup['values']);
          if (!$contact['is_deceased']) {
            $contact_update = array(
              'id'            => $contact['id'],
              'is_deceased'   => 1
              );

            // calculate the deceased date
            $deceased_date = $this->getPropagationValue($context->btx, $match, $config->set_deceased_date);
            if ($deceased_date) {
              $contact_update['deceased_date'] = date('YmdHis', strtotime($deceased_date));
            }
            civicrm_api3('Contact', 'create', $contact_update);
            $this->logMessage("Contact [{$contact['id']}] marked as deceased.", 'info');
          }

          // set Tag in any case
          if (is_array($config->tag_contact)) {
            $this->tagContact($contact_id, $config->tag_contact);
          }
        }
      }
    }
  }
}

