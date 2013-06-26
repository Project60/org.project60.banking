<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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
    
    return CRM_Utils_System::url($base, $pstring);
}