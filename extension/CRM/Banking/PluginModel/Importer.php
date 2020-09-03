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

require_once 'CRM/Banking/Helpers/OptionValue.php';

use CRM_Banking_ExtensionUtil as E;

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_Importer extends CRM_Banking_PluginModel_IOPlugin {

  // these are the fields valid for a BTX record.
  protected $_primary_btx_fields = array('version', 'debug', 'amount', 'bank_reference', 'value_date', 'booking_date', 'currency', 'type_id', 'status_id', 'data_raw', 'data_parsed', 'ba_id', 'party_ba_id', 'tx_batch_id', 'sequence');
  // these fields will be used to determine, if this is a duplicate record... the primary keys if you want
  protected $_compare_btx_fields = array('bank_reference' => TRUE, 'amount' => TRUE, 'value_date' => TRUE, 'booking_date' => TRUE, 'currency' => TRUE, 'version' => 3);
  // if this is set, all checkAndStoreBTX() methods will be added to it
  protected $_current_transaction_batch = NULL;
  protected $_current_transaction_batch_attributes = array();
  protected $_default_btx_state_id = 0;

  // this will be used to avoid multiple account lookups
  protected $account_cache = array();

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
  abstract function probe_file($file_path, $params);

  /**
   * Import the given file
   *
   * @return TODO: data format?
   */
  abstract function import_file($file_path, $params);

  /**
   * Test if the configured source is available and ready
   *
   * @var
   * @return TODO: data format?
   */
  abstract function probe_stream($params);

  /**
   * Import from the configured source
   *
   * @return TODO: data format?
   */
  abstract function import_stream($params);

  /**
   * class constructor
   */
  function __construct($plugin_dao) {
    parent::__construct($plugin_dao);
    $this->_default_btx_state_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'new');

    // restrict the lookup for the organisation's (own) bank account (see #178)
    $config = $this->_plugin_config;
    if (!isset($config->organisation_contact_ids)) $config->organisation_contact_ids = '';
    if (!isset($config->organisation_ba_ids))      $config->organisation_ba_ids = '';
  }

  // ------------------------------------------------------
  //            utility functions
  // ------------------------------------------------------

  /**
   * try identify the two bank accounts involved,
   * and set the party_ba_id and ba_id fields
   */
  protected function lookupBankAccounts(&$data) {
    foreach ($data as $key => $value) {
      // check for NBAN_?? or IBAN endings
      if (preg_match('/^_.*NBAN_..$/', $key) || preg_match('/^_.*IBAN$/', $key)) {
        // this is a *BAN entry -> look it up
        if (!isset($this->account_cache[$value])) {
          // not cached? ok, do a lookup:
          $reference_search_params = array(
            'reference'    => $value,
            'return'       => 'ba_id',
            'option.limit' => 0);

          // add ba_restriction
          if (!empty($this->_plugin_config->organisation_ba_ids)) {
            $reference_search_params['ba_id'] = array('IN' => explode(',', $this->_plugin_config->organisation_ba_ids));
          }

          // search for references
          $this->logMessage("Looking up bank account reference: " . json_encode($reference_search_params), 'debug');
          $reference_search = civicrm_api3('BankingAccountReference', 'get', $reference_search_params);
          $potential_ba_ids = array();
          foreach ($reference_search['values'] as $reference) {
            $potential_ba_ids[] = $reference['ba_id'];
          }

          if (!empty($potential_ba_ids) && !empty($this->_plugin_config->organisation_contact_ids)) {
            // apply the restriction to contact IDs
            $ba_search_params = array(
              'contact_id'   => array('IN' => explode(',', $this->_plugin_config->organisation_contact_ids)),
              'id'           => array('IN' => $potential_ba_ids),
              'return'       => 'id',
              'option.limit' => 0);
            $this->logMessage("Looking up bank account: " . json_encode($ba_search_params), 'debug');
            $ba_search = civicrm_api3('BankingAccount', 'get', $ba_search_params);

            // reset potential_ba_ids
            $potential_ba_ids = array();
            foreach ($ba_search['values'] as $ba) {
              $potential_ba_ids[] = $ba['id'];
            }
          }

          // cache the result
          if (count($potential_ba_ids) == 1) {
            // found exactly 1!
            $this->account_cache[$value] = $potential_ba_ids[0];
          } else {
            $this->account_cache[$value] = NULL;
          }
        }

        if ($this->account_cache[$value] != NULL) {
          if (substr($key, 0, 7)=="_party_") {
            $data['party_ba_id'] = $this->account_cache[$value];
          } elseif (substr($key, 0, 1)=="_") {
            $data['ba_id'] = $this->account_cache[$value];
          }
        }
      }
    }
  }


  /**
   * This will create a new transaction batch, that all bankt transcations created
   * with checkAndStoreBTX will be attached to. The transaction gets written when calling
   * the corresponding closeTransactionBatch counterpart.
   *
   * You can also re-use and extend a given btx batch by providing a batch ID
   */
  function openTransactionBatch($batch_id = 0) {
    if ($this->_current_transaction_batch == NULL) {
      $this->_current_transaction_batch = new CRM_Banking_BAO_BankTransactionBatch();
      $this->_current_transaction_batch_attributes = array();

      if ($batch_id) {
        // load an existing batch
        $this->_current_transaction_batch->get('id', $batch_id);
        $this->_current_transaction_batch_attributes['isnew'] = FALSE;
        $this->_current_transaction_batch_attributes['sum'] = ($this->_current_transaction_batch->ending_balance - $this->_current_transaction_batch->starting_balance);
      } else {
        // TODO: \/ why are the defaults not generated by CRM_Banking_BAO_BankTransactionBatch::add() ???
        $this->_current_transaction_batch->issue_date = date('YmdHis');
        $this->_current_transaction_batch->reference = '';
        $this->_current_transaction_batch->sequence = 0;
        $this->_current_transaction_batch->tx_count = 0;
        //       /\

        // add a (unique) default reference (see https://github.com/Project60/CiviBanking/issues/60)
        $this->_current_transaction_batch->reference = 'NOREF-' . md5(microtime());
        $this->_current_transaction_batch->save();
        $this->_current_transaction_batch_attributes['isnew'] = TRUE;
        $this->_current_transaction_batch_attributes['sum'] = 0;
      }
    } else {
      $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE, E::ts("Internal error: trying to open BTX batch before closing an old one."), CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
    }
  }

  /**
   * This will return the current BTX batch as a BAO to the client for modification.
   * Please DON'T SAVE THE OBJECT. Saving should take place when calling the
   * closeTransactionBatch() method.
   */
  function getCurrentTransactionBatch($store = TRUE) {
    return $this->_current_transaction_batch;
  }

  /**
   * This will close a previously opened transaction batch, see openTransactionBatch
   *
   * If you pass $store=FALSE as a parameter, the currently open batch will be dismissed
   */
  function closeTransactionBatch($store = TRUE) {
    if ($this->_current_transaction_batch != NULL) {
      if ($store) {

        // check if the sums are correct:
        if ($this->_current_transaction_batch->ending_balance) {
          $sum_in_bao = $this->_current_transaction_batch->ending_balance - $this->_current_transaction_batch->starting_balance;
          $deviation = $sum_in_bao - $this->_current_transaction_batch_attributes['sum'];
          $correct_value = $this->_current_transaction_batch->starting_balance + $this->_current_transaction_batch_attributes['sum'];
          if (abs($deviation) > 0.005) {
            // there is a (too big) deviation!
            if ($this->_current_transaction_batch->ending_balance) { // only log if it was set
              $this->reportProgress(0.0, sprintf(E::ts("Adjusted ending balance from %s to %s!"), $this->_current_transaction_batch->ending_balance, $correct_value), CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN);
            }
            $this->_current_transaction_batch->ending_balance = $correct_value;
          }
        } else if ($this->_current_transaction_batch->starting_balance != NULL) {
          // set the calculated ending balance only if the was a starting balance set
          $this->_current_transaction_batch->ending_balance = $this->_current_transaction_batch->starting_balance + $this->_current_transaction_batch_attributes['sum'];
        }

        // set the dates
        if (!$this->_current_transaction_batch->starting_date && isset($this->_current_transaction_batch_attributes['starting_date']))
          $this->_current_transaction_batch->starting_date = $this->_current_transaction_batch_attributes['starting_date'];
        if (!$this->_current_transaction_batch->ending_date && isset($this->_current_transaction_batch_attributes['ending_date']))
          $this->_current_transaction_batch->ending_date = $this->_current_transaction_batch_attributes['ending_date'];

        // set default bank reference, if not set
        if (   $this->_current_transaction_batch->reference == NULL
            || substr($this->_current_transaction_batch->reference, 0, 6) == 'NOREF-') {
          // replace generic references, starting with 'NOREF-'
          $this->_current_transaction_batch->reference = "{md5}";
        }

        // replace tokens
        $reference = $this->_current_transaction_batch->reference;
        $dateFormat = 'Y-m-d';  // FIXME: read config
        $reference = str_replace('{md5}', md5($this->_current_transaction_batch_attributes['references']), $reference);
        $reference = str_replace('{starting_date}', date($dateFormat, strtotime($this->_current_transaction_batch->starting_date)), $reference);
        $reference = str_replace('{ending_date}', date($dateFormat, strtotime($this->_current_transaction_batch->ending_date)), $reference);

        // make sure, this reference doesn't exist yet
        $final_reference = $reference;
        $counter = 0;
        $query_params = array(
          1 => array($final_reference, 'String'),
          2 => array($this->_current_transaction_batch->id, 'Integer'));
        $query_sql = "SELECT COUNT(id) FROM civicrm_bank_tx_batch WHERE reference = %1 AND id != %2;";
        while (CRM_Core_DAO::singleValueQuery($query_sql, $query_params)) {
          $counter += 1;
          $final_reference = "DUPLICATE-{$counter}-{$reference}";
          $query_params[1] = array($final_reference, 'String');
        }

        $this->_current_transaction_batch->reference = substr($final_reference, 0, 64);
        $this->_current_transaction_batch->save();
      } else if ($this->_current_transaction_batch_attributes['isnew']) {
        // since the batch object had to be created in order to get the ID, we would have to
        //  delete it here, if the user didn't want to keep it.
        $this->_current_transaction_batch->delete();
      }
      $this->_current_transaction_batch = NULL;
    } else {
      $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE, E::ts("Internal error: trying to close a nonexisting BTX batch."), CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
    }
  }

  /**
   * Will update the transaction information, which is collected for validation
   */
  function _updateTransactionBatchInfo($btx) {
    if ($this->_current_transaction_batch) {
      // update simple counters
      $this->_current_transaction_batch->tx_count += 1;
      $attribs = &$this->_current_transaction_batch_attributes;
      $attribs['sum'] += (float) $btx['amount'];

      // keep track of dates
      if (!isset($attribs['starting_date'])) {
        $attribs['starting_date'] = $btx['booking_date'];
      } else if (strtotime($attribs['starting_date']) > strtotime($btx['booking_date'])) {
        // the new transaction is before the current starting date:
        $attribs['starting_date'] = $btx['booking_date'];
      }
      if (!isset($attribs['ending_date'])) {
        $attribs['ending_date'] = $btx['booking_date'];
      } else if (strtotime($attribs['ending_date']) < strtotime($btx['booking_date'])) {
        // the new transaction is after the current ending date:
        $attribs['ending_date'] = $btx['booking_date'];
      }

      // update bank reference list
      if (!isset($attribs['references'])) {
        $attribs['references'] = $btx['bank_reference'];
      } else {
        $attribs['references'] = $attribs['references'] . $btx['bank_reference'];
      }

      // test currency
      if ($this->_current_transaction_batch->currency && isset($btx['currency'])) {
        if ($this->_current_transaction_batch->currency != $btx['currency']) {
          $this->reportProgress(CRM_Banking_PluginModel_Base::REPORT_PROGRESS_NONE, E::ts("WARNING: multiple currency batches not fully supported"), CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN);
        }
      } else {
        $this->_current_transaction_batch->currency = $btx['currency'];
      }
    }
  }

  /**
   * This method will take an array with all the attributes for a bank transaction object,
   * check whether this object already exists, and create a new data entry if not.
   * In case the object exists, the existing entry is returned.
   * If the client wants to merge the data, this has to be done by the client.
   *
   * @return TRUE, if successful, FALSE if not, or a duplicate existing BTX as property array
   */
  function checkAndStoreBTX($btx, $progress, $params = array()) {
    // make sure the version is set
    $btx['version'] = 3;

    // first, test for duplicates:
    $duplicate_test = array_intersect_key($btx, $this->_compare_btx_fields);
    $result = civicrm_api('BankingTransaction', 'get', $duplicate_test);
    if (isset($result['is_error']) && $result['is_error']) {
      $this->reportProgress($progress, E::ts("Failed to query BTX."), CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
      return FALSE;
    }

    if ($result['count'] > 0) {
      // there might be another BTX...check the accounts
      $duplicates = $result['values'];
      $this->reportProgress($progress, E::ts("Duplicate BTX entry detected. Not imported!"), CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN);
      return reset($duplicates); // RETURN FIRST ENTRY
    }

    // set default state
    if (!isset($btx['status_id']) || $btx['status_id'] <= 0) {
      $btx['status_id'] = $this->_default_btx_state_id;
    }

    // check if booking_date is properly set
    if (empty($btx['booking_date']) || strtotime($btx['booking_date'])===FALSE) {
      $this->reportProgress($progress, E::ts("No valid booking date detected. Not imported!"), CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
      return FALSE;
    }

    // check if value_date is properly set
    if (empty($btx['value_date']) || strtotime($btx['value_date'])===FALSE) {
      $this->reportProgress($progress, E::ts("No valid value date detected. Not imported!"), CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
      return FALSE;
    }

    // now store...
    // check for dry run
    if (isset($params['dry_run']) && $params['dry_run'] == "on") {
      // DRY RUN ENABLED
      $log_entry = E::ts("DRY RUN: Did not create bank transaction (%1 on %2)",
                    array(  1 => CRM_Utils_Money::format($btx['amount'], $btx['currency']),
                            2 => CRM_Utils_Date::customFormat($btx['booking_date'], CRM_Core_Config::singleton()->dateformatFull)));
      $this->reportProgress($progress, $log_entry);
      return TRUE;
    } else {
      // attach to the transaction batch, if there is an open one
      if ($this->_current_transaction_batch) {
        $btx['tx_batch_id'] = $this->_current_transaction_batch->id;
      }

      $result = civicrm_api('BankingTransaction', 'create', $btx);
      if ($result['is_error']) {
        $this->reportProgress(
                $progress,
                sprintf(E::ts("Error while storing BTX: %s"),implode("<br>", $result)),
                CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
        return FALSE;
      } else {
        $log_entry = E::ts("Created BTX <b>%1</b> for <b>%2</b> on %3",
                    array(  1 => $result['id'],
                            2 => CRM_Utils_Money::format($btx['amount'], $btx['currency']),
                            3 => CRM_Utils_Date::customFormat($btx['booking_date'], CRM_Core_Config::singleton()->dateformatFull)));
        $this->reportProgress($progress, $log_entry);
        $this->_updateTransactionBatchInfo($btx);
        return TRUE;
      }
    }
  }


  /**
   * helper function for prefix testing
   */
  protected function startsWith($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
  }

}

