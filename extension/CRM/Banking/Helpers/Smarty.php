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
 * This class is a Smarty wrapper to allow pre 4.6 CiviCRM versions
 *  to use push/pop operations
 */
class CRM_Banking_Helpers_Smarty {

  protected $smarty       = NULL;
  protected $backupFrames = array();

  /**
   * Static instance provider.
   *
   * Method providing static instance of SmartTemplate, as
   * in Singleton pattern.
   */
  public static function &singleton() {
    if (version_compare(CRM_Utils_System::version(), '4.6', '<')) {
      // < 4.6: we need to create a wrapper
      $smarty = CRM_Core_Smarty::singleton();
      $smarty_wrapper = new CRM_Banking_Helpers_Smarty($smarty);
      return $smarty_wrapper;
    } else {
      // >= 4.6: just use the core smarty implementation
      return CRM_Core_Smarty::singleton();
    }
  }

  /**
   * Class constructor
   *
   * @return CRM_Core_Smarty
   */
  private function __construct($smarty) {
    $this->smarty = $smarty;
    $this->backupFrames = array();
  }

  // pass through fetch()
  public function fetch($resource_name, $cache_id = NULL, $compile_id = NULL, $display = FALSE) {
    return $this->smarty->fetch($resource_name, $cache_id, $compile_id, $display);
  }

  // DON'T pass through assign(), that messes up the backup frame
  public function assign($key, $value) {
    throw new Exception("Smarty::assign() is not allowed within a backup frame.");
    //return $this->smarty->assign($key, $value);
  }



  /**
   * Temporarily assign a list of variables.
   *
   * @code
   * $smarty->pushScope(array(
   *   'first_name' => 'Alice',
   *   'last_name' => 'roberts',
   * ));
   * $html = $smarty->fetch('view-contact.tpl');
   * $smarty->popScope();
   * @endcode
   *
   * @param array $vars
   *   (string $name => mixed $value).
   * @return CRM_Core_Smarty
   * @see popScope
   */
  public function pushScope($vars) {
    $oldVars = $this->smarty->get_template_vars();
    $backupFrame = array();
    foreach ($vars as $key => $value) {
      $backupFrame[$key] = isset($oldVars[$key]) ? $oldVars[$key] : NULL;
    }
    $this->backupFrames[] = $backupFrame;

    $this->assignAll($vars);

    return $this;
  }

  /**
   * Remove any values that were previously pushed.
   *
   * @return CRM_Core_Smarty
   * @see pushScope
   */
  public function popScope() {
    $this->assignAll(array_pop($this->backupFrames));
    return $this;
  }

  /**
   * @param array $vars
   *   (string $name => mixed $value).
   * @return CRM_Core_Smarty
   */
  public function assignAll($vars) {
    foreach ($vars as $key => $value) {
      $this->smarty->assign($key, $value);
    }
    return $this;
  }
}