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
 * This PostProcessor can update the contact's address with the one from the bank statement
 */
class CRM_Banking_PluginImpl_PostProcessor_AddressUpdate extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->default_location_type))        $config->default_location_type        = CRM_Core_BAO_LocationType::getDefault()->id;
    if (!isset($config->default_country))              $config->default_country              = CRM_Core_BAO_Country::defaultContactCountry();
    if (!isset($config->required_fields))              $config->required_fields              = array("city", "postal_code", "street_address");
    if (!isset($config->btx_field_prefix))             $config->btx_field_prefix             = '';
    if (!isset($config->create_if_missing))            $config->create_if_missing            = true;
    if (!isset($config->create_diff))                  $config->create_diff                  = ['note']; // also accepts 'activity' and 'tag'
    if (!isset($config->create_diff_if_missing))       $config->create_diff_if_missing       = false;
    if (!isset($config->create_diff_activity_type))    $config->create_diff_activity_type    = 1;
    if (!isset($config->create_diff_activity_subject)) $config->create_diff_activity_subject = E::ts("New Address Received");
    if (!isset($config->create_diff_activity_status_id)) $config->create_diff_activity_status_id = NULL; // "Completed"
    if (!isset($config->tag_diff))                     $config->tag_diff                     = array();
    if (!isset($config->tag_create))                   $config->tag_create                   = array();

    // TODO: implement create_diff = ['api']
  }

  /**
   * @inheritDoc
   */
  protected function shouldExecute(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    $preview = FALSE
  ) {
    $config = $this->_plugin_config;

    // check if there is a single contact
    $contact_id = (
      $preview ?
        $match->getParameter('contact_id')
          ?: $match->getParameter('contact_ids')
        : $this->getSoleContactID($context));
    if (empty($contact_id)) {
      return FALSE;
    }

    // check if there is enough data
    $prefix = $config->btx_field_prefix;
    $data   = $context->btx->getDataParsed();
    foreach ($config->required_fields as $required_field) {
      if (empty($data[$prefix . $required_field])) {
        return FALSE;
      }
    }

    // pass on to parent to check generic reasons
    return parent::shouldExecute($match, $matcher, $context, $preview);
  }

  public function previewMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context
  ) {
    $preview = NULL;
    $config = $this->_plugin_config;
    if (
      $this->shouldExecute(
      $match,
      $matcher,
      $context,
      TRUE
    )
      && (
        !empty($config->create_diff)
        || !empty($config->create_if_missing)
      )
    ) {
      $preview = '<ul>';
      if (in_array('note', $config->create_diff)) {
        $preview .= '<li>A note will be created on the contact denoting differing address data.</li>';
      }
      if (in_array('activity', $config->create_diff)) {
        $preview .= '<li>An activity will be created denoting differing address data.</li>';
      }
      if (in_array('tag', $config->create_diff)) {
        $preview .= '<li>A tag will be created on the contact denoting differing address data.</li>';
      }
      if ($config->create_if_missing) {
        $preview .= '<li>If the contact does not have an address, a new address will be created.</li>';
      }
      $preview .= '</ul>';
    }
    return $preview;
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
    if (!$this->shouldExecute($match, $matcher, $context)) {
      // TODO: log: not executing...
      return;
    }

    $config = $this->_plugin_config;
    $prefix = $config->btx_field_prefix;
    $data   = $context->btx->getDataParsed();

    // this matcher only makes sense for individuals
    $contact_id = $this->getSoleContactID($context);
    if (empty($contact_id)) {
      // this shouldn't happen, since it's checked in shouldExecute
      return;
    }

    // compile what we have
    $address_fields = array('location_type_id', 'postal_code', 'street_address', 'city', 'country_id', 'is_primary', 'is_billing');
    $address_data = array();
    foreach ($address_fields as $address_field) {
      $field_key = $prefix . $address_field;
      if (isset($data[$field_key]) && $data[$field_key] != '') {
        $address_data[$address_field] = $data[$field_key];
      }
    }

    // fill defaults
    if (empty($address_data['country_id'])) {
      $address_data['country_id'] = $config->default_country;
    }
    $this->resolveCountryID($address_data);
    if (empty($address_data['location_type_id'])) {
      $address_data['location_type_id'] = $config->default_location_type;
    }
    $this->addCountryName($address_data);

    // now: find the given address
    $existing_addresses = civicrm_api3('Address', 'get', array(
      'location_type_id' => $address_data['location_type_id'],
      'contact_id'       => $contact_id));

    if ($existing_addresses['count'] == 0 && $config->create_if_missing) {
      // config wants us to creaete a new address:
      $address_data['contact_id'] = $contact_id;
      civicrm_api3('Address', 'create', $address_data);

      // and tag it
      if (is_array($config->tag_create)) {
        $this->tagContact($contact_id, $config->tag_create);
      }

    } elseif (
      ($existing_addresses['count'] == 0 && $config->create_diff_if_missing)
      || !empty($existing_addresses['id'])
    ) {
      // CREATE DIFF
      $existing_address = reset($existing_addresses['values']) ?: [];
      $this->addCountryName($existing_address);
      $diff = array();
      foreach ($address_data as $key => $value) {
        $existing_value = CRM_Utils_Array::value($key, $existing_address);
        if ($value != $existing_value) {
          $diff[$key] = array($existing_value, $value);
        }
      }

      // only continue if there is a difference
      if (!empty($diff)) {
        if (is_array($config->create_diff)) {
          foreach ($config->create_diff as $action) {
            $this->createDiff($action, $contact_id, $diff, $existing_address, $address_data);
          }
        }
      }

    } else {
      // there's multiple addresses
      $this->logMessage("Multiple addresses found. Not doing anything.", 'error');
    }
  }

  /**
   * process the given action
   */
  protected function createDiff($action, $contact_id, $diff, $existing_address, $address_data) {
    $config = $this->_plugin_config;
    switch ($action) {
      case 'note':
        $smarty = CRM_Banking_Helpers_Smarty::singleton();
        $smarty->pushScope(array('contact_id' => $contact_id, 'diff' => $diff, 'existing_address' => $existing_address, 'address_data' => $address_data));
        $note = $smarty->fetch('CRM/Banking/PluginImpl/PostProcessor/AddressUpdate.note.tpl');
        $smarty->popScope();

        // check if the same note already exists
        $existing_note = civicrm_api3('Note', 'get', array(
          'note'         => $note,
          'subject'      => E::ts("New Address Received"),
          'entity_table' => 'civicrm_contact',
          'entity_id'    => $contact_id));
        if ($existing_note['count'] == 0) {
          civicrm_api3('Note', 'create', array(
            'note'         => $note,
            'subject'      => E::ts("New Address Received"),
            'entity_table' => 'civicrm_contact',
            'entity_id'    => $contact_id));
        }
        break;

      case 'tag':
        if ($config->tag_diff) {
          $this->tagContact($contact_id, $config->tag_diff);
        }
        break;

      case 'activity':
        $smarty = CRM_Banking_Helpers_Smarty::singleton();
        $smarty->pushScope(array('contact_id' => $contact_id, 'diff' => $diff, 'existing_address' => $existing_address, 'address_data' => $address_data));
        $details = $smarty->fetch('CRM/Banking/PluginImpl/PostProcessor/AddressUpdate.activity.tpl');
        $smarty->popScope();

        $activity_params = [
          'subject'          => $config->create_diff_activity_subject,
          'details'          => $details,
          'activity_type_id' => $config->create_diff_activity_type,
          'target_id'        => $contact_id
        ];
        if (!empty($config->create_diff_activity_status_id)) {
          $activity_params['status_id'] = $config->create_diff_activity_status_id;
        }
        civicrm_api3('Activity', 'create', $activity_params);
        break;

      default:
        $this->logMessage("Unknown action '{$action}' no diff created.", 'error');
    }
  }

  /**
   * fill the address' country field with the name
   */
  protected function addCountryName(&$address) {
    if (!empty($address['country_id'])) {
      try {
        if (is_numeric($address['country_id'])) {
          $country = civicrm_api3('Country', 'getsingle', array('id' => $address['country_id']));
          $address['country'] = $country['name'];
        } elseif (strlen($address['country_id']) == 2) {
          $country = civicrm_api3('Country', 'getsingle', array('iso_code' => strtoupper($address['country_id'])));
          $address['country'] = $country['name'];
        } else {
          $address['country'] = "ERROR";
        }
      } catch (Exception $e) {
        $address['country'] = "ERROR";
      }
    }
  }


  /**
   * make sure the country_id is numeric
   */
  protected function resolveCountryID(&$address) {
    if (isset($address['country_id'])) {
      try {
        if (is_numeric($address['country_id'])) {
          return; // all good here
        } elseif (strlen($address['country_id']) == 2) {
          $country = civicrm_api3('Country', 'getsingle', array('iso_code' => strtoupper($address['country_id'])));
          $address['country_id'] = $country['id'];
        } else {
          unset($address['country_id']);
        }
      } catch (Exception $e) {
        unset($address['country_id']);
      }
    }
  }
}

