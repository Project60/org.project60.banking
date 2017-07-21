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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_BtxBase extends CRM_Banking_PluginModel_Base {

  /**
   * Checks if the preconditions defined by required_values are met
   *
   * @return TRUE iff all values are as expected (or none are specified)
   */
  public function requiredValuesPresent(CRM_Banking_BAO_BankTransaction &$btx, $required_values_override = NULL) {
    $config = $this->_plugin_config;

    if ($required_values_override) {
      $required_values = $required_values_override;
      if (is_array($required_values)) {
        // make sure this is an object...
        $required_values = json_decode(json_encode($required_values));
      }
    } else {
      if (empty($config->required_values)) {
        $required_values = NULL;
      } else {
        $required_values = $config->required_values;
      }
    }

    // nothing specified => ALL CLEAR
    if (empty($required_values)) return TRUE;

    // check array type
    if (is_object($required_values)) {
      // this is an ASSOCIATIVE array
      foreach ($required_values as $required_key => $required_value) {
        $current_value = $this->getPropagationValue($btx, NULL, $required_key);
        $split = split(':', $required_value, 2);
        if (count($split) < 2) {
          error_log("org.project60.banking: required_value in config option not properly formatted, plugin id [{$this->_plugin_id}]");
        } else {
          $command = $split[0];
          $parameter = $split[1];
          if ($command == 'equal_constant') {
            if ($current_value != $parameter) return FALSE;

          } elseif ($command == 'equal') {
            $compare_value = $this->getPropagationValue($btx, NULL, $parameter);
            if ($current_value != $compare_value) return FALSE;

          } elseif ($command == 'in_constant') {
            $exptected_values = explode(",", $parameter);
            if (in_array($current_value, $exptected_values)) {
              continue;
            } else {
              return FALSE;
            }

          } elseif ($command == 'in') {
            $list_value = $this->getPropagationValue($btx, NULL, $parameter);
            $exptected_values = explode(",", $list_value);
            if (in_array($current_value, $exptected_values)) {
              continue;
            } else {
              return FALSE;
            }

          } elseif ($command == 'type') {
            switch ($parameter) {
              case 'positiveInteger':
                $value = (int) $current_value;
                if ($value > 0) { continue; } else { return FALSE; }

              case 'Integer':
                $value = (int) $current_value;
                if ($value >= 0) { continue; } else { return FALSE; }

              case 'numeric':
                if (is_numeric($current_value)) { continue; } else { return FALSE; }

              default:
                error_log("Unknown required type: {$parameter}");
                return FALSE;
            }

            $value = $this->getPropagationValue($btx, NULL, $parameter);

            $exptected_values = explode(",", $list_value);
            if (in_array($current_value, $exptected_values)) {
              continue;
            } else {
              return FALSE;
            }

          } elseif ($command == 'not_in_constant') {
            $exptected_values = explode(",", $parameter);
            if (!in_array($current_value, $exptected_values)) {
              continue;
            } else {
              return FALSE;
            }

          } elseif ($command == 'not_in') {
            $list_value = $this->getPropagationValue($btx, NULL, $parameter);
            $exptected_values = explode(",", $list_value);
            if (!in_array($current_value, $exptected_values)) {
              continue;
            } else {
              return FALSE;
            }

          } else {
            error_log("org.project60.banking: unknwon command '$command' in required_value in config of plugin id [{$this->_plugin_id}]");
          }
        }
      }

    } elseif (is_array($required_values)) {
      // this is a SEQUENTIAL array -> simply check if they are there
      foreach ($config->required_values as $required_key) {
        if ($this->getPropagationValue($btx, NULL, $required_key)==NULL) {
          // there is no value given for this key => bail
          return FALSE;
        }
      }

    } else {
      error_log("org.project60.banking: WARNING: required_values config option not properly set, plugin id [{$this->_plugin_id}]");
    }

    return TRUE;
  }


  /**************************************************************
   *                   value propagation                        *
   * allows for a config-driven propagation of extracted values *
   *                                                            *
   * Propagation values are data that has been gathered some-   *
   * where during the process. This data (like financial_type,  *
   * campaign_id, etc.) can then be passed on to the final      *
   * objects, e.g. contributions                                *
   **************************************************************/

  /**
   * Get the propagation keys
   * If a subset (e.g. 'contribution') is given, only
   * the keys targeting this entity are returned
   */
  public function getPropagationKeys($subset='', $propagation_config = NULL) {
    if ($propagation_config==NULL) {
      if (isset($this->_plugin_config->value_propagation)) {
        $propagation_config = $this->_plugin_config->value_propagation;
      } else {
        $propagation_config = NULL;
      }
    }
    $keys = array();
    if (!isset($propagation_config) || $propagation_config===NULL) {
      return $keys;
    }

    foreach ($propagation_config as $key => $target_key) {
      if ($subset) {
        if (substr($target_key, 0, strlen($subset))==$subset) {
          $keys[$key] = $target_key;
        }
      } else {
        $keys[$key] = $target_key;
      }
    }
    return $keys;
  }

  /**
   * Get the value of the propagation value spec
   */
  public function getPropagationValue($btx, $suggestion, $key) {
    $key_bits = split("[.]", $key, 2);
    if ($key_bits[0]=='ba' || $key_bits[0]=='party_ba') {
      // read bank account stuff
      if ($key_bits[0]=='ba') {
        $bank_account = $btx->getBankAccount();
      } else {
        $bank_account = $btx->getPartyBankAccount();
      }

      if ($bank_account==NULL) {
        return NULL;
      }

      if (isset($bank_account->$key_bits[1])) {
        // look in the BA directly
        return $bank_account->$key_bits[1];
      } else {
        // look in the parsed values
        $data = $bank_account->getDataParsed();
        if (isset($data[$key_bits[1]])) {
          return $data[$key_bits[1]];
        } else {
          return NULL;
        }
      }

    } elseif ($key_bits[0]=='suggestion' || $key_bits[0]=='match') {
      // read suggestion parameters
      if ($suggestion != NULL) {
        return $suggestion->getParameter($key_bits[1]);
      } else {
        return NULL;
      }

    } elseif ($key_bits[0]=='btx') {
      // read BTX stuff
      if (isset($btx->$key_bits[1])) {
        // look in the BA directly
        return $btx->$key_bits[1];
      } else {
        // look in the parsed values
        $data = $btx->getDataParsed();
        if (isset($data[$key_bits[1]])) {
          return $data[$key_bits[1]];
        } else {
          return NULL;
        }
      }
    }

    return NULL;
  }

  /**
   * Get the key=>value set of the propagation values
   *
   * if a subset is specified (e.g. 'contribution')
   * only the targets with prefix contribution will be
   * read, and the
   *
   * example:
   *   in config:  '"value_propagation": { "party_ba.name": "contribution.source" }'
   *
   * with getPropagationSet($btx, 'contribution') you get:
   *   array("source" => "Looked-up bank owner's name")
   *
   * ...which you can pass right into your create contribtion call
   */
  public function getPropagationSet($btx, $suggestion, $subset = '', $propagation_config = NULL) {
    $propagation_set = $this->getPropagationKeys($subset, $propagation_config);
    $propagation_values = array();

    foreach ($propagation_set as $key => $target_key) {
      $value = $this->getPropagationValue($btx, $suggestion, $key);
      if ($value != NULL) {
        $propagation_values[substr($target_key, strlen($subset)+1)] = $value;
      }
    }

    return $propagation_values;
  }


  /**************************************************************
   *                    Matching Variables                      *
   **************************************************************/
  /**
   * Will get the list of variables defined by the "variables" tag in the config
   *
   * @return array of variable names
   */
  function getVariableList() {
    if (isset($this->_plugin_config->variables)) {
      $variables = (array) $this->_plugin_config->variables;
      return array_keys($variables);
    }
    return array();
  }

  /**
   * Will get a variable as defined by the "variables" tag in the config
   *
   * @param $context  CRM_Banking_Matcher_Context instance, will be used for caching
   * @return the variables length
   */
  function getVariable($name) {
    if (isset($this->_plugin_config->variables->$name)) {
      $variable_spec = $this->_plugin_config->variables->$name;
      if (!empty($variable_spec->cached)) {
        // check the cache
        $value = CRM_Utils_StaticCache::getCachedEntry('var_'.$name);
        if ($value != NULL) {
          return $value;
        }
      }

      // get value
      if ($variable_spec->type == 'SQL') {
        $value = array();
        $querySQL = "SELECT " . $variable_spec->query . ";";
        try {
          $query = CRM_Core_DAO::executeQuery($querySQL);
          while ($query->fetch()) {
            $value[] = $query->value;
          }
        } catch (Exception $e) {
          error_log("org.project60.banking.matcher: there was an error with SQL statement '$querySQL'");
        }
        if (isset($variable_spec->glue)) {
          $value = implode($variable_spec->glue, $value);
        }
      } else {
        error_log("org.project60.banking.matcher: unknown variable type '{$variable_spec->type}'.");
      }

      if (!empty($variable_spec->cached)) {
        // set cache value
        CRM_Utils_StaticCache::setCachedEntry('var_'.$name, $value);
      }

      return $value;
    }

    return NULL;
  }
}
