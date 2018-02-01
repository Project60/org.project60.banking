<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
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
 * This class represents a "Matching Rule" as used
 *  by the rule-based matcher: CRM_Banking_PluginImpl_Matcher_Rules
 */
class CRM_Banking_Rules_Rule {

  /**
   * Load a rule based on ID
   */
  public static function getRule($rule_id) {
    // TODO
  }


  /**
   * get rule's ID
   */
  public function getID() {
    // TODO
    return 0;
  }

  /**
   * Return the type of rule:
   *  'analyser rules' will only update the btx data, while
   *  'matcher rules'  will actually go ahead and create contributions
   */
  public function isAnalyserRule() {
    return TRUE; // for now we'll only implement these
  }

  /**
   * Adds all parameters needed to the given
   * variable set. These will end up in the
   * smarty template
   */
  public function addRenderParameters(&$variables) {
    // TODO
  }

}