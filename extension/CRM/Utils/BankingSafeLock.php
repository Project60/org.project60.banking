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

/**
 * This class extends the current CiviCRM lock
 * by a security mechanism to prevent a process from
 * acquiring two or more locks.
 * This, due to the nature of the underlying implementation
 * would RELEASE the previously acquired lock
 */
class CRM_Utils_BankingSafeLock {

  private static $_acquired_lock         = NULL;

  private $lock;
  private $name;
  private $counter;

  private function __construct($civilock, $lockname) {
    $this->lock = $civilock;
    $this->name = $lockname;
    $this->counter = 1;
  }

  public function getName() {
    return $this->name;
  }

  /**
   * Will acquire a lock with the given name, 
   * if no other lock has been acquired by this process.
   * 
   * If the same lock has been acquired before (and not been released),
   * in internal counter is increased. Therefore you can acquire the same
   * lock multiple times, but you will then have to release them 
   * the same amount of times
   *
   * @return a SafeLock instance or NULL if timed out
   */
  public static function acquireLock($name, $timeout=60) {
    if (self::$_acquired_lock == NULL) {
      // it's free, we'll try to take it
      $lock = new CRM_Core_Lock($name, $timeout);
      if (version_compare(CRM_Utils_System::version(), '4.6', '>=')) {
        // before 4.6, a new lock would be automatically acquired
        $lock->acquire();
      }
      if ($lock!=NULL && $lock->isAcquired()) {
        // we got it!
        self::$_acquired_lock = new CRM_Utils_BankingSafeLock($lock, $name);
        return self::$_acquired_lock;
      } else {
        // timed out
        return NULL;
      }

    } elseif (self::$_acquired_lock->getName() == $name) {
      // this means acquiring 'our' lock again:
      $lock = self::$_acquired_lock;
      $lock->counter += 1;
      //error_log('acquired ' . getmypid() . "[{$lock->counter}]");
      return $lock;

    } else {
      // this is the BAD case: somebody's trying to acquire ANOTHER LOCK,
      //  while we still own another one
      $lock_name = self::$_acquired_lock->getName();
      throw new Exception("This process cannot acquire more than one lock! It still owns lock '$lock_name'.");
    }

  }

  /**
   * Will release a lock with the given name, 
   *  if it has been acquired before
   */
  public static function releaseLock($name) {
    if (self::$_acquired_lock == NULL) {
      // weird, we don't own this lock...
      error_log("org.project60.banking: This process cannot release lock '$name', it has not been acquired.");
      throw new Exception("This process cannot release lock '$name', it has not been acquired.");

    } elseif (self::$_acquired_lock->getName() == $name) {
      // we want to release our own lock
      self::$_acquired_lock->release();

    } else {
      // somebody is trying to release ANOTHER LOCK
      $lock_name = self::$_acquired_lock->getName();
      error_log("org.project60.banking: This process cannot realease lock '$name', it still owns lock '$lock_name'.");
      throw new Exception("This process cannot realease lock '$name', it still owns lock '$lock_name'.");
    }
  }

  /**
   * Will release a lock with the given name, 
   *  if it has been acquired before
   */
  public function release() {
    if ($this->counter > 1) {
      // this is a lock that we acquired multiple times:
      //  simply decrease counter
      $this->counter -= 1;
      //error_log('released ' . getmypid() . "[{$this->counter}]");

    } elseif ($this->counter == 1) {
      // simply release the lock
      $this->counter = 0;
      $this->lock->release();
      self::$_acquired_lock = NULL;
      //error_log('released ' . getmypid());

    } else {
      // lock has already been released!
      error_log("org.project60.banking: This process cannot realease lock '$name', it has already been released before.");
      throw new Exception("This process cannot realease lock '$name', it has already been released before.");   
    }
  }
}