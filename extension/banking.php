<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+-------------------------------------------------------*/

require_once '../banking.civix.php';
use CRM_Banking_ExtensionUtil as E;

CRM_Core_Session::setStatus(
    E::ts("CiviBanking Path Issue"),
    E::ts("If you can see this message, the latest CiviBanking code update has caused an issue with the extension's code. "
        ."Please refresh the CiviCRM cache repeatedly until this messages goes away."),
    'alert',
    ['expires' => 'never']
);
