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

use CRM_Banking_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Banking_Upgrader extends CRM_Banking_Upgrader_Base {

  /**
   * Create table for Rules Matcher/Analyser
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0610() {
    $this->ctx->log->info('Applying update 0610');
    $this->executeSqlFile('sql/banking.sql');

    // update rebuild log tables
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();

    return TRUE;
  }
}
