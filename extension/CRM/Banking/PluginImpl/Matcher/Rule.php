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
 * The RULE matcher implements a rule-based scheme for matching. The structure 
 * of such a rule is described in YAML like this :
 * 
 * ruleName:
 *   weight: NNN
 *   if:
 *     - atomicTerm
 *     - paramTerm:
 *          key: value
 *          key: value
 *          prob: NN
 *   then:
 *     - atomicAction
 *     - paramAction:
 *          parameter: value
 *   mode: auto
 *   threshold: NN
 * 
 * which translates into 
 * 
 * { "ruleName": {
 *        "threshold": "NN", 
 *        "mode": "auto", 
 *        "weight": "NNN", 
 *        "if": [
 *            "atomicTerm",
 *            { "paramsTerm": {
 *                "prob": "NN", 
 *                "key": "value"
 *                }
 *              }
 *            ],
 *        "then": [
 *            "atomicAction", 
 *            { "paramAction": {
 *                "parameter": "value"
 *                }
 *              }
 *            ] 
 *        }
 * }
 * 
 * The RULE matcher converts this to an executable instruction set to be interpreted
 * by the RULE base engine. All terms and actions are enclosed in separate classes. 
 * 
 * We need to figure out how to describe the suggestion text though ... may not be as easy as it looks in a generic way
 */
class CRM_Banking_PluginImpl_Matcher_Rule extends CRM_Banking_PluginModel_Matcher {

  protected $conditions;

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);
    $this->parseConditions();
  }

  protected function parseConditions() {
    $this->conditions = array();
    $config = $this->json_decode($this->_plugin_config);
    foreach ($config->if as $cond) {
      $c = $this->createCondition($cond);
      if ($c) {
        $this->conditions[] = $c;
      } else {
        // some error
      }
    }
  }

  /**
   * Should create an instance of a CRM_Banking_Condition subclass
   * 
   * @param type $spec
   * @return string
   */
  protected function createCondition($spec) {
    echo '<hr>';
    print_r($spec);
    return new CRM_Banking_Condition_Generic( $spec );    // temporary, should be subclass instance
  }

  /**
   * Run the rule's IF specification
   * 
   * @param type $btx
   * @param type $context
   * @return type
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    // this section will be refactored to use different conditions, but for now, this is hardcoded
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);

    foreach ($this->conditions as $condition) {
      $condition->match($btx, $context, $suggestion);
    }

    if ($suggestion->getProbability() > 0) {
      $this->addSuggestion($suggestion);
    }

    // close up
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }


  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($match, $btx) {
    
  }

  function visualize_match(CRM_Banking_Matcher_Suggestion $match, $btx) {
    $s = '<ul>'.ts("Because :");
    $evidence = $match->getEvidence();
    foreach ($evidence as $ev) {
      $s .= '<li>' . $ev . '</li>';
    }
    $s .= '</ul>';
    return $s;
  }

}

