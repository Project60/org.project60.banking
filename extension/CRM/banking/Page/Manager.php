<?php

require_once 'CRM/Core/Page.php';

class CRM_Banking_Page_Manager extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Manage CiviBanking Components'));

    parent::run();
  }
}
