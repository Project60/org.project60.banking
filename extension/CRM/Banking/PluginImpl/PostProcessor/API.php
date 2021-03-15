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
 * This PostProcessor call an API action if triggered
 */
class CRM_Banking_PluginImpl_PostProcessor_API extends CRM_Banking_PluginModel_PostProcessor {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->entity))            $config->entity = NULL;
    if (!isset($config->action))            $config->action = NULL;
    if (!isset($config->params))            $config->params = array();
    if (!isset($config->loop))              $config->loop =   array();
    if (!isset($config->param_propagation)) $config->param_propagation = array();
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

    // check if an entity is set
    if (empty($config->entity)) {
      return FALSE;
    }

    // check if an action is set
    if (empty($config->action)) {
      return FALSE;
    }

    // pass on to parent to check generic reasons
    return parent::shouldExecute($match, $matcher, $context, $preview);
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
      // compile call parameters
      $params = array();
      foreach ($config->params as $key => $value) {
        if ($value !== NULL) {
          $params[$key] = $value;
        }
      }

      foreach ($config->param_propagation as $value_source => $value_key) {
        $value = $this->getPropagationValue($context->btx, $match, $value_source);
        if ($value !== NULL) {
          $params[$value_key] = $value;
        }
      }

      // perform the call(s)
      try {
        if (!empty($config->loop) && is_array($config->loop)) {
          // there is a loop command in here
          $this->loopCall($context, $config, $params, 1);

        } else {
          // no loop -> just execute
          $this->logMessage("CALLING {$config->entity}.{$config->action} with " . json_encode($params), 'debug');
          civicrm_api3($config->entity, $config->action, $params);
        }

      } catch (Exception $e) {
        $this->logMessage("CALLING {$config->entity}.{$config->action} failed: " . $e->getMessage(), 'error');
      }
    }
  }

  /**
   * Will recursively call the API based on the entries in the 'loop' params
   */
  protected function loopCall($context, $config, $params, $level) {
    if ($level <= count($config->loop)) {
      if (is_object($config->loop[$level-1]) || is_array($config->loop[$level-1])) {
        // ok, all clear -> start looping level $level
        foreach ($config->loop[$level-1] as $attribute => $source) {
          $values = $this->getLoopValues($context, $source);
          // these are the values to loop over in this level
          foreach ($values as $value) {
            $params[$attribute] = $value;
            if ($level < count($config->loop)) {
              // this is not the lowest level -> recursive call
              $this->loopCall($context, $config, $params, $level+1);
            } else {
              // this IS the last level, DO the call
              $this->logMessage("CALLING {$config->entity}.{$config->action} with " . json_encode($params), 'debug');
              civicrm_api3($config->entity, $config->action, $params);
            }
          }
        }
      } else {
        $this->logMessage("loop paramater is incorrectly structured.", 'error');
      }
    }
  }

  /**
   * get a list of values from the field
   */
  protected function getLoopValues($context, $source) {
    $value = $this->getPropagationValue($context->btx, $match, $source);

    if ($value === NULL) {
      return array();
    } elseif (is_array($value)) {
      // oh, this is already an array
      return $value;
    } else {
      // check if it is JSON data
      $json_list = json_decode($value, TRUE);
      if ($json_list && is_array($json_list)) {
        return $json_list;
      }

      // last resort: split by ','
      return explode(',', $value);
    }
  }
}

