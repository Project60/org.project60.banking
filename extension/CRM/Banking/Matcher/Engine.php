<?php
/*
  org.project60.banking extension for CiviCRM

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */

require_once 'CRM/Banking/Helpers/OptionValue.php';
require_once 'CRM/Banking/Helpers/Lock.php';

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
  public function match( CRM_Banking_BAO_BankTransaction $btx, $override_processed = FALSE ) {
    $lock = banking_helper_getLock('tx', $btx->id);
    if (!$lock->isAcquired()) {
      error_log("org.project60.banking - couldn't acquire lock. Timeout is ".$lock->_timeout);
      return false;
    }

    error_log("matching ".$btx->id);
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
    
    // run through the list of matchers
    if (empty($this->plugins)) {
      CRM_Core_Session::setStatus(ts("No matcher plugins configured!"), ts('No processors'), 'alert');
    } else {
      foreach ($this->plugins as $weight => $plugins) {
        foreach ($plugins as $plugin) {
          // run matchers to generate suggestions
          $continue = $this->matchPlugin( $plugin, $context );
          if (!$continue) {
            $lock->release();
            return true;
          }

          // check if we can execute the suggestion right aways
          $abort = $this->checkAutoExecute($plugin, $btx);
          if ($abort) {
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

    error_log("done matching ".$btx->id);
    $lock->release();
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
            $lock = banking_helper_getLock('tx', $btx->id);
            if (!$lock->isAcquired()) {
              error_log("org.project60.banking - couldn't acquire lock. Timeout is ".$lock->_timeout);
              continue;
            }

            $btx->saveSuggestions();
            $result = $suggestion->execute( $btx, $plugin );

            $lock->release();
            return $result;
          }
        }
      }
    }
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
      $btx_bao = new CRM_Banking_BAO_BankTransaction();
      $btx_bao->get('id', $unprocessed_id);
      $this->match($btx_bao);
    }
    return count($unprocessed_ids);
  }
}