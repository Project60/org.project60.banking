<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
      self::$singleton = new CRM_Banking_Matcher_Engine();
    }
    return self::$singleton;
  }

  //----------------------------------------------------------------------------
  //
  // INSTANCE METHODS


  private $plugins;
  private $matchers = NULL;
  private $postprocessors = NULL;


  /**
   * Initialize this instance
   */
  private function getMatchers() {
    if ($this->matchers === NULL) {
      $this->matchers = array();
      $matcher_type_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.plugin_classes', 'match');
      $params = array('version' => 3, 'plugin_type_id' => $matcher_type_id, 'enabled' => 1);
      $result = civicrm_api('BankingPluginInstance', 'get', $params);
      if (isset($result['is_error']) && $result['is_error']) {
        CRM_Core_Session::setStatus(E::ts("Error while trying to query database for matcher plugins!"), E::ts('No processors'), 'alert');
      } else {
        foreach ($result['values'] as $instance) {
          $pi_bao = new CRM_Banking_BAO_PluginInstance();
          $pi_bao->get('id', $instance['id']);

          // add to array wrt the weight
          if (!isset($this->matchers[$pi_bao->weight])) $this->matchers[$pi_bao->weight] = array();
          array_push($this->matchers[$pi_bao->weight], $pi_bao->getInstance());
        }
      }

      // sort array by weight
      ksort($this->matchers);
    }
    return $this->matchers;
  }




  /**
   * read the list of currently active postprocessors in the right execution order
   */
  private function getPostprocessors() {
    if ($this->postprocessors == NULL) {
      $this->postprocessors = array();

      $postprocessor_type_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.plugin_classes', 'postprocess');
      $params = array('version' => 3, 'plugin_type_id' => $postprocessor_type_id, 'enabled' => 1);
      $result = civicrm_api('BankingPluginInstance', 'get', $params);
      if (isset($result['is_error']) && $result['is_error']) {
        CRM_Core_Session::setStatus(E::ts("Error while trying to query database for postprocessor plugins!"), E::ts('No processors'), 'alert');
      } else {
        foreach ($result['values'] as $instance) {
          $pi_bao = new CRM_Banking_BAO_PluginInstance();
          $pi_bao->get('id', $instance['id']);

          // add to array wrt the weight
          if (!isset($this->postprocessors[$pi_bao->weight])) $this->postprocessors[$pi_bao->weight] = array();
          array_push($this->postprocessors[$pi_bao->weight], $pi_bao->getInstance());
        }
      }

      // sort array by weight
      ksort($this->postprocessors);
    }

    return $this->postprocessors;
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
    // run through the list of matchers
    $all_matchers = $this->getMatchers();
    if (empty($all_matchers)) {
      CRM_Core_Session::setStatus(E::ts("No matcher plugins configured!"), E::ts('No processors'), 'alert');
    } else {
      foreach ($all_matchers as $weight => $matchers) {
        foreach ($matchers as $matcher) {
          try {
            // run matchers to generate suggestions
            $logger->setTimer('matcher');
            $continue = $this->matchPlugin( $matcher, $context );
            $logger->logTime("Matcher [{$matcher->getPluginID()}]", 'matcher');

            if (!$continue) {
              $lock->release();
              $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
              return true;
            }

            // check if we can execute the suggestion right aways
            $abort = $this->checkAutoExecute($matcher, $btx);
            if ($abort) {
              $logger->logDebug("Matcher [{$matcher->getPluginID()}] executed automatically.");
              $lock->release();
              $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
              return false;
            }
          } catch (Exception $e) {
            $matcher_id = $matcher->getPluginID();
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
    $context->destroy();
    $logger->logTime("Matching of btx [{$btx_id}]", 'matcher');
    return false;
  }

  /**
   * Previews the execution of post processors on a given suggestion.
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param CRM_Banking_PluginModel_Matcher $matcher
   */
  public function previewPostProcessors($suggestion, $btx, $matcher) {
    $context = new CRM_Banking_Matcher_Context($btx);
    $all_postprocessors = $this->getPostprocessors();
    $previews = [];
    foreach ($all_postprocessors as $weight => $postprocessors) {
      foreach ($postprocessors as $postprocessor) {
        /* @var CRM_Banking_PluginModel_PostProcessor $postprocessor */
        try {
          // Check for NULL. An empty string is considered a valid preview.
          if (!is_null($preview = $postprocessor->previewMatch(
            $suggestion,
            $matcher,
            $context
          ))) {
            $previews[$postprocessor->getName()] = $preview;
          }
        } catch (Exception $e) {
          $matcher_id = $matcher->getPluginID();
          error_log("org.project60.banking - Exception during the preview of postprocessor [$matcher_id], error was: ".$e->getMessage());
        }
      }
    }
    return $previews;
  }

  /**
   * will run the postprocessors on the recently executed match
   *
   * @param \CRM_Banking_Matcher_Suggestion $suggestion
   */
  public function runPostProcessors($suggestion, $btx, $matcher) {
    // run through the list of matchers
    $logger = CRM_Banking_Helpers_Logger::getLogger();
    $logger->setTimer('postprocessing');

    $context = new CRM_Banking_Matcher_Context( $btx );
    $context->setExecutedSuggestion($suggestion);
    $all_postprocessors = $this->getPostprocessors();
    foreach ($all_postprocessors as $weight => $postprocessors) {
      foreach ($postprocessors as $postprocessor) {
        /* @var CRM_Banking_PluginModel_PostProcessor $postprocessor */
        try {
          $logger->setTimer('postprocessor');
          $logger->logDebug("Calling PostProcessor [{$postprocessor->getName()}]...");
          $result = $postprocessor->processExecutedMatch($suggestion, $matcher, $context);
          if ($result !== FALSE) {
            $suggestion->setExecutedPostprocessor($postprocessor, $result);
            $btx->saveSuggestions();
          }
          $logger->logTime("Postprocessor [{$postprocessor->getPluginID()}]", 'postprocessor');

        } catch (Exception $e) {
          $matcher_id = $matcher->getPluginID();
          error_log("org.project60.banking - Exception during the execution of postprocessor [$matcher_id], error was: ".$e->getMessage());
        }
      }
    }

    $logger->logTime("Postprocessing of btx [{$btx->id}]", 'postprocessing');
    $context->destroy();
  }

  /**
   * Visualizes the execution of post processors on a given suggestion.
   *
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param CRM_Banking_PluginModel_Matcher $matcher
   */
  public function visualizePostProcessorResults($suggestion, $btx, $matcher) {
    $context = new CRM_Banking_Matcher_Context($btx);
    $results = [];
    $all_postprocessors = $this->getPostprocessors();
    $executed_postprocessors = $suggestion->getExecutedPostprocessors() ?: [];
    foreach ($all_postprocessors as $weight => $postprocessors) {
      foreach ($postprocessors as $postprocessor) {
        /* @var CRM_Banking_PluginModel_PostProcessor $postprocessor */
        if (array_key_exists($plugin_id = $postprocessor->getPluginId(), $executed_postprocessors)) {
          $result = $executed_postprocessors[$plugin_id];
          try {
            if (!empty($result = $postprocessor->visualizeExecutedMatch($suggestion, $matcher, $context, $result))) {
              $results[] = $result;
            }
          } catch (Exception $e) {
            $matcher_id = $matcher->getPluginID();
            error_log("org.project60.banking - Exception during the visualization of results of postprocessor [$matcher_id], error was: ".$e->getMessage());
          }
        }
      }
    }
    return $results;
  }

  /**
   * Test if the given plugin can execute a suggestion right away
   *
   * @param $plugin CRM_Banking_PluginModel_Matcher
   * @param $btx    CRM_Banking_BAO_BankTransaction
   * @return true iff the plugin was executed and the payment is fully processed
   */
  protected function checkAutoExecute($plugin, $btx) {
    if (!$plugin->autoExecute()) return false;
    foreach ($btx->getSuggestions() as $suggestions ) {
      foreach ($suggestions as $suggestion) {
        /* @var $suggestion CRM_Banking_Matcher_Suggestion */
        if ($suggestion->getPluginID() == $plugin->getPluginID()) {

          // check if the suggestion requires user confirmation
          $user_confirmation_required = $suggestion->getUserConfirmation();
          if (!empty($user_confirmation_required)) {
            // there is some user confirmation required, NO AUTO EXEC!
            continue;
          }

          // check if the probability is high enough
          if ($suggestion->getProbability() >= $plugin->autoExecute()) {
            // all good, AUTO EXEC:
            $btx->saveSuggestions();
            $transaction = new CRM_Core_Transaction();
            $result = $suggestion->execute($btx);
            $suggestion->setParameter('executed_automatically', 1);
            $btx->saveSuggestions();
            $transaction->commit();
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