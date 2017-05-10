<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

require_once 'CRM/Banking/Helpers/OptionValue.php';

class CRM_Banking_Matcher_Engine {

  // CLASS METHODS

  static private $singleton = null;

  public static function getInstance() {
    if (self::$singleton === null) {
      $bm = new CRM_Banking_Matcher_Engine();
      $bm->init();
      self::$singleton = $bm;
    }
    return self::$singleton;
  }

  //----------------------------------------------------------------------------
  //
  // INSTANCE METHODS

  private $plugins;

  /**
   * Initialize this instance
   */
  private function init() {
    $this->initPlugins();
  }

  /**
   * Initialize the list of plugins
   */
  private function initPlugins() {
    // perform a BAO query to select all active match plugins and insert instances for them into
    //    the matchers array by weight, then ksort descending
    $this->plugins = array();

    $matcher_type_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.plugin_classes', 'match');
    $params = array('version' => 3, 'plugin_type_id' => $matcher_type_id, 'enabled' => 1);
    $result = civicrm_api('BankingPluginInstance', 'get', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Error while trying to query database for matcher plugins!"), ts('No processors'), 'alert');
    } else {
      foreach ($result['values'] as $instance) {
        $pi_bao = new CRM_Banking_BAO_PluginInstance();
        $pi_bao->get('id', $instance['id']);

        // add to array wrt the weight
        if (!isset($this->plugins[$pi_bao->weight])) $this->plugins[$pi_bao->weight] = array();
        array_push($this->plugins[$pi_bao->weight], $pi_bao->getInstance());
      }
    }

    // sort array by weight
    ksort($this->plugins);
  }

  /**
   * Run this BTX through the matchers
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param bool $override_processed   Set this to TRUE if you want to re-match processed transactions.
   *                                    This will destroy all records of the execution!
   */
  public function match( $btx_id, $override_processed = FALSE ) {
    // TODO: timeout is 30s - do we need a setting here?
    $lock_timeout = 30.0;
    $lock = CRM_Utils_BankingSafeLock::acquireLock('org.project60.banking.tx'.'-'.$btx_id, $lock_timeout);
    if (empty($lock)) {
      error_log("org.project60.banking - couldn't acquire lock. Timeout is $lock_timeout.");
      return false;
    }

    // load btx
    $btx = new CRM_Banking_BAO_BankTransaction();
    $btx->get('id', $btx_id);


    if (!$override_processed) {
      // don't match already executed transactions...
      $processed_status_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
      $ignored_status_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Ignored');
      if ($btx->status_id == $processed_status_id || $btx->status_id == $ignored_status_id) {
        // will not match already executed transactions
        $lock->release();
        return true;
      }
    }

    // reset the BTX suggestion list
    $btx->resetSuggestions();

    // reset the cache / context object
    $context = new CRM_Banking_Matcher_Context( $btx );
    $logger = CRM_Banking_Helpers_Logger::getLogger();

    // run through the list of matchers
    $logger->setTimer('matching');
    if (empty($this->plugins)) {
      CRM_Core_Session::setStatus(ts("No matcher plugins configured!"), ts('No processors'), 'alert');
    } else {
      foreach ($this->plugins as $weight => $plugins) {
        foreach ($plugins as $plugin) {
          try {
            // run matchers to generate suggestions
            $logger->setTimer('matcher');
            $continue = $this->matchPlugin( $plugin, $context );
            $logger->logTime("Matcher [{$plugin->getPluginID()}]", 'matcher');

            if (!$continue) {
              $lock->release();
              $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
              return true;
            }

            // check if we can execute the suggestion right aways
            $abort = $this->checkAutoExecute($plugin, $btx);
            if ($abort) {
              $logger->logDebug("Matcher [{$plugin->getPluginID()}] executed automatically.");
              $lock->release();
              $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
              return false;
            }
          } catch (Exception $e) {
            $matcher_id = $plugin->getPluginID();
            error_log("org.project60.banking - Exception during the execution of matcher [$matcher_id], error was: ".$e->getMessage());
            $lock->release();
            return false;
          }
        }
      }
    }
    $btx->saveSuggestions();

    // set the status
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Suggestions');
    $btx->status_id = $newStatus;
    $btx->setStatus($newStatus);

    $lock->release();
    $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
    return false;
  }

  /**
   * Test if the given plugin can execute a suggestion right away
   *
   * @return true iff the plugin was executed and the payment is fully processed
   */
  protected function checkAutoExecute($plugin, $btx) {
    if (!$plugin->autoExecute()) return false;
    foreach ($btx->getSuggestions() as $suggestions ) {
      foreach ($suggestions as $suggestion) {
        if ($suggestion->getPluginID()==$plugin->getPluginID()) {
          if ($suggestion->getProbability() >= $plugin->autoExecute()) {
            $btx->saveSuggestions();
            $result = $suggestion->execute( $btx, $plugin );
            $suggestion->setParameter('executed_automatically', 1);
            $btx->saveSuggestions();
            return $result;
          }
        }
      }
    }
    return false;
  }

  /**
   * Run a single plugin to check for a match
   *
   * @param type $plugin
   * @param type $btx
   * @param type $context
   */
   protected function matchPlugin( CRM_Banking_PluginModel_Matcher $plugin, CRM_Banking_Matcher_Context $context ) {

    $btx = $context->btx;

    // match() returns an instance of CRM_Banking_Matcher_Suggestion
    $suggestions = $plugin->match( $btx, $context );
    if ($suggestions !== null) {
      // handle the possibility to get multiple matches in return
      if (!is_array($suggestions)) $suggestions = array( $suggestions->probability => $suggestions );
    }
    return true;
  }


  /**
   * Bulk-run a set of <n> unprocessed items
   *
   * @param $max_count       the maximal amount of bank transactions to process
   *
   * @return the actual amount of bank transactions prcoessed
   */
  public function bulkRun($max_count) {
    $unprocessed_ids = CRM_Banking_BAO_BankTransaction::findUnprocessedIDs($max_count);
    foreach ($unprocessed_ids as $unprocessed_id) {
      $this->match($unprocessed_id);
    }
    return count($unprocessed_ids);
  }
}