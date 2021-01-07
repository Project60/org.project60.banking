<?php

/*-------------------------------------------------------+
| Project 60 - CiviBanking - PHPUnit tests               |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This is a mock for CRM_Banking_Matcher_Engine.
 *
 * NOTE: The only change this class does is removing the singleton feature because otherwise a list of matchers is
 *       cached after the first used and never updated for following tests (the same is true for the postprocessors).
 *       As soon as possible should the CRM_Banking_Matcher_Engine be changed to either
 *       a) be no singleton,
 *       or b) use no cache,
 *       or c) have a possibility to clear the cache,
 *       or d) have the cache be protected instead of private for proper mocking.
 */
class CRM_Banking_Mocking_CRMBankingMatcherEngine extends CRM_Banking_Matcher_Engine
{
    public static function getInstance()
    {
        $matcher = new CRM_Banking_Matcher_Engine();

        return $matcher;
    }
}
