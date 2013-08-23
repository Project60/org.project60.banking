<?php

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Dashboard extends CRM_Core_Page {
  function run() {
    CRM_Utils_System::setTitle(ts('CiviBanking Dashboard'));

    parent::run();
  }
}
