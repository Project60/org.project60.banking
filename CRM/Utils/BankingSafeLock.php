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

declare(strict_types = 1);

/**
 * This class extends the current CiviCRM lock
 * by a security mechanism to prevent a process from
 * acquiring two or more locks.
 * This, due to the nature of the underlying implementation
 * would RELEASE the previously acquired lock
 */
class CRM_Utils_BankingSafeLock {

  private static ?self $acquiredLock = NULL;

  private int $counter = 1;

  private function __construct(
    private readonly CRM_Core_Lock $lock,
    private readonly string $name
  ) {}

  public function getName(): string {
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
   * @return self|null SafeLock instance or NULL if timed out
   */
  public static function acquireLock(string $name, int $timeout = 60): ?self {
    if (self::$acquiredLock === NULL) {
      // it's free, we'll try to take it
      $lock = new CRM_Core_Lock($name, $timeout);
      if ($lock->acquire()) {
        // we got it!
        self::$acquiredLock = new CRM_Utils_BankingSafeLock($lock, $name);
        return self::$acquiredLock;
      }
      else {
        // timed out
        return NULL;
      }
    }
    elseif (self::$acquiredLock->getName() === $name) {
      // this means acquiring 'our' lock again:
      $lock = self::$acquiredLock;
      $lock->counter += 1;

      return $lock;
    }
    else {
      // this is the BAD case: somebody's trying to acquire ANOTHER LOCK,
      //  while we still own another one
      $lockName = self::$acquiredLock->getName();
      throw new RuntimeException("This process cannot acquire more than one lock! It still owns lock '$lockName'.");
    }

  }

  /**
   * Will release a lock with the given name,
   *  if it has been acquired before
   */
  public static function releaseLock(string $name): void {
    if (self::$acquiredLock === NULL) {
      // weird, we don't own this lock...
      error_log("org.project60.banking: This process cannot release lock '$name', it has not been acquired.");
      throw new RuntimeException("This process cannot release lock '$name', it has not been acquired.");

    }
    elseif (self::$acquiredLock->getName() === $name) {
      // we want to release our own lock
      self::$acquiredLock->release();
    }
    else {
      // somebody is trying to release ANOTHER LOCK
      $lockName = self::$acquiredLock->getName();
      error_log("org.project60.banking: This process cannot realease lock '$name', it still owns lock '$lockName'.");
      throw new RuntimeException("This process cannot realease lock '$name', it still owns lock '$lockName'.");
    }
  }

  /**
   * Will release a lock with the given name,
   *  if it has been acquired before
   */
  public function release(): void {
    if ($this->counter > 1) {
      // this is a lock that we acquired multiple times:
      //  simply decrease counter
      $this->counter -= 1;
    }
    elseif ($this->counter === 1) {
      // simply release the lock
      $this->counter = 0;
      $this->lock->release();
      self::$acquiredLock = NULL;
    }
    else {
      // lock has already been released!
      error_log('org.project60.banking: This process cannot release the lock, it has already been released before.');
      throw new RuntimeException("This process cannot release the lock', it has already been released before.");
    }
  }

}
