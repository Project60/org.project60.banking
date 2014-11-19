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
 * Get a batching lock
 * 
 * the lock is needed so that only one relevant process can access the 
 * payment/statment data structures at a time
 * 
 * @return lock object. check if it ->isAcquired() before use
 */
function banking_helper_getLock($type, $id) {
  if ($type=='tx') {
    $timeout = 30.0; // TODO: do we need a setting here?
    return new CRM_Utils_SafeLock('org.project60.banking.tx'.'-'.$id, $timeout);
  } else {
    error_log("org.project60.banking - Lock of type '$type' not known.");
    return NULL;
  }
}
