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


class CRM_Banking_Matcher_Engine {
  
  // CLASS METHODS

  static private $singleton = null;
  
  public static function getInstance() {
    if (self::$singleton === null) {
      $bm = new BankingMatcher();
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
    // perform a BAO query to select all active match plugins and insert instances for them into the matchers array by weight, then ksort descending
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
    foreach ($this->plugins as $weight => $plugins) {
      foreach ($plugins as $plugin) {
        $continue = $this->matchPlugin( $plugin, $context );
        if (!$continue) return true;
      }
    }
    
    $btx->saveSuggestions();
    return false;
  }
  
  /**
   * Run a single plugin to check for a match
   * 
   * @param type $plugin
   * @param type $btx
   * @param type $context
   */
  private function matchPlugin( CRM_Banking_PluginModel_Matcher $plugin, CRM_Banking_Matcher_Context $context ) {
    $btx = $context->btx;
    
    // match() returns an instance of CRM_Banking_Matcher_Suggestion
    $suggestions = $plugin->match( $btx, $context );
    if ($suggestions !== null) {
      // handle the possibility to get multiple matches in return
      if (!is_array($suggestions)) $suggestions = array( $suggestions->probability => $suggestions );
      
      // process matches
      foreach ($suggestions as $probability => $suggestion ) {
        $btx->addSuggestion( $suggestion );
        if ($suggestion->probability >= $plugin->threshold) {
          if ($plugin->auto_execute == 1) {
            $btx->saveSuggestions();
            $continue = $suggestion->execute( $btx, $plugin );
            if (!$continue) return false;
          }
        }
      }
    }
    return true;
  }
  
  
  
}