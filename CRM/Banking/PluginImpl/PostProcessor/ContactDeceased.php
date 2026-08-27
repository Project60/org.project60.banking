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

declare(strict_types = 1);

use Webmozart\Assert\Assert;

/**
 * This PostProcessor will mark the matched contact as 'deceased'
 */
class CRM_Banking_PluginImpl_PostProcessor_ContactDeceased extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  public function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->set_deceased_date)) {
      $config->set_deceased_date = 'btx.booking_date';
    }
    if (!isset($config->tag_contact)) {
      $config->tag_contact = [];
    }
    if (!isset($config->contribution_fields_required)) {
      $config->contribution_fields_required = 'contact_id';
    }
  }

  /**
   * @inheritDoc
   */
  public function processExecutedMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context
  ): ?bool {
    $config = $this->_plugin_config;

    if ($this->shouldExecute($match, $matcher, $context)) {
      $contact_id = $this->getSoleContactID($context);
      assert(NULL !== $contact_id);

      $contact_lookup = civicrm_api3('Contact', 'get', [
        'id'     => $contact_id,
        'return' => 'is_deceased,is_deleted,deceased_date,id',
      ]);
      if ($contact_lookup['id']) {
        // mark contact as deceased
        $contact = reset($contact_lookup['values']);
        if (!$contact['is_deceased']) {
          $contact_update = [
            'id'            => $contact['id'],
            'is_deceased'   => 1,
          ];

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
          Assert::allString($config->tag_contact);
          $this->tagContact($contact_id, $config->tag_contact);
        }
      }

      return NULL;
    }

    return FALSE;
  }

  public function shouldExecute(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    bool $preview = FALSE
  ): bool {
    return parent::shouldExecute($match, $matcher, $context, $preview)
      && ($preview || NULL !== $this->getSoleContactID($context));
  }

}
