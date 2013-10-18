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
  }
  
  /**
   * Run this BTX through the matchers
   * 
   * @param CRM_Banking_BAO_BankTransaction $btx
   */
  public function match( CRM_Banking_BAO_BankTransaction $btx ) {
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
          $continue = $this->matchPlugin( $plugin, $context );
          if (!$continue) return true;
        }
      }
    }    
    
    // process matches
    foreach ($btx->getSuggestions() as $probability => $suggestions ) {
      foreach ($suggestions as $suggestion) {
        if ($suggestion->getProbability() > $plugin->getThreshold()) {
          if ($plugin->autoExecute()) {
            $btx->saveSuggestions();
//            die('executing auto');
            $continue = $suggestion->execute( $btx, $plugin );
            if (!$continue) return false;
          }
        }
      }
    }
    
    $btx->saveSuggestions();

    // set the status
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Suggestions');
    $btx->status_id = $newStatus;
    $btx->setStatus($newStatus);

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
  
  
  
}