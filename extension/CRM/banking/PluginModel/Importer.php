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
abstract class CRM_Banking_PluginModel_Importer extends CRM_Banking_PluginModel_IOPlugin {

  // these are the fields valid for a BTX record.
  protected $_primary_btx_fields = ['version', 'debug', 'amount', 'bank_reference', 'value_date', 'booking_date', 'currency', 'type_id', 'status_id', 'data_raw', 'data_parsed', 'ba_id', 'party_ba_id', 'tx_batch_id', 'sequence' ];

  // these fields will be used to determine, if this is a duplicate record... the primary keys if you want
  protected $_compare_btx_fields = ['bank_reference'=>TRUE, 'amount'=>TRUE, 'value_date'=>TRUE, 'booking_date'=>TRUE, 'currency'=>TRUE, 'version'=>3];
  
  // ------------------------------------------------------
  // Functions to be provided by the plugin implementations
  // ------------------------------------------------------
  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  static function does_import_files() {
    return false;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_import_stream() {
    return false;
  }

  /** 
   * Test if the given file can be imported
   * 
   * @var 
   * @return TODO: data format? 
   */
  abstract function probe_file( $file_path, $params );

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  abstract function import_file( $file_path, $params );

  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  abstract function probe_stream( $params );

  /** 
   * Import from the configured source
   * 
   * @return TODO: data format?
   */
  abstract function import_stream( $params );


  // ------------------------------------------------------
  //            utility functions
  // ------------------------------------------------------

  /**
   * This method will take an array with all the attributes for a bank transaction object,
   * check whether this object already exists, and create a new data entry if not.
   * In case the object exists, the existing entry is returned.
   * If the client wants to merge the data, this has to be done by the client.
   *
   * @return TRUE, if successful, FALSE if not, or a duplicate existing BTX as property array
   */
  function checkAndStoreBTX($btx, $progress, $params=array()) {
    // first, test for duplicates:
    $duplicate_test = array_intersect_key($btx, $this->_compare_btx_fields);
    $result = civicrm_api('BankingTransaction', 'get', $duplicate_test);
    if (isset($result['is_error']) && $result['is_error']) {
      $this->reportProgress($progress, 
                            ts("Failed to query BTX."), 
                            CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
      return FALSE;
    }

    if ($result['count']>0) {
      // there might be another BTX...check the accounts
      $duplicates = $result['values'];
      $this->reportProgress($progress, 
                        ts("Duplicate BTX entry detected. Not imported!"), 
                        CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN);
      return reset($duplicates); // RETURN FIRST ENTRY
    }

    // now store 
    if (isset($params['dry_run']) && $params['dry_run']=="on") {
      // DRY RUN ENABLED
      $this->reportProgress($progress, 
                            sprintf(ts("DRY RUN: Did not create bank transaction '%d' (%f %s on %s)"), $result['id'], $btx['amount'], $btx['currency'], $btx['booking_date']));
      return TRUE;
    } else {
      $result = civicrm_api('BankingTransaction', 'create', $btx);
      if ($result['is_error']) {
        $this->reportProgress($progress, 
                              sprintf(ts("Error while storing BTX: %s") ,implode("<br>", $result)),
                              CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
        return FALSE;
      } else {
        $this->reportProgress($progress, 
                              sprintf(ts("Created bank transaction '%d' (%f %s on %s)"), $result['id'], $btx['amount'], $btx['currency'], $btx['booking_date']));
        return TRUE;
      }
    }
  }


  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);

  }
}

