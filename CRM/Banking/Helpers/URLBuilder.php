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


function banking_helper_buildURL($base, $set_params, $keep_params=array(), $delete_params=array(), $keep_param_source=NULL) {
    if ($keep_param_source==NULL) $keep_param_source = $_REQUEST;

    $params = array();
    // take over selected parameters from sourc
    foreach ($keep_params as $key) {
        if (isset($keep_param_source[$key])) {
            $params[$key] = $keep_param_source[$key];
        }
    }

    // add (and override) the parameters
    foreach ($set_params as $key => $value) {
        $params[$key] = $value;
    }

    // remove requested parameters
    foreach ($delete_params as $key) {
        unset($params[$key]);
    }


    // build string:
    $pstring = '';
    foreach ($params as $key => $value) {
        if (strlen($pstring)>0) $pstring = $pstring."&";
        $pstring = $pstring.$key."=".$value;
    }

    return CRM_Utils_System::url($base, $pstring, FALSE, NULL, FALSE);
}
