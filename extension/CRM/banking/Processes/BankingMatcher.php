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

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */

/**
 * main matching function, will do the following:
 *  - read order and config of matchers 
 *  - instatiate matchers
 *  - loop through all $btxs (bank transactions), and run them through every matcher, generating suggestions
 *  - save these suggestions and the updated state to each btx
 *  - report progress, error and success messages to $progress_callback
 * 
 * @var $btx_list           list of bank transactions to match. ¡Caution! will be processed again, regardless of state
 * @var $params             TODO
 * @var $progress_callback  callback object to receive progress messages
 */
function crm_banking_process_btxs($btx_list, $params, $progress_callback)
{
  
}


