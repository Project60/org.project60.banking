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
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
abstract class CRM_Banking_PluginModel_Exporter extends CRM_Banking_PluginModel_IOPlugin {

  // ------------------------------------------------------
  // Functions to be provided by the plugin implementations
  // ------------------------------------------------------
  /** 
   * Report if the plugin is capable of exporting files
   * 
   * @return bool
   */
  abstract function does_export_files();

  /** 
   * Report if the plugin is capable of exporting streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  abstract function does_export_stream();

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return URL of the resulting file
   */
  abstract function export_file( $txbatch2ids, $parameters );

  /** 
   * Export the given btxs
   * 
   * $txbatch2ids array(<tx_batch_id> => array(<tx_id>))
   *
   * @return bool TRUE if successful
   */
  abstract function export_stream( $txbatch2ids, $parameters );



  /**
   * will evaluate the 'list' (comma separated list of tx IDs) and 
   * 's_list' (comma separated list of tx_batch IDs), if given.
   *
   * @return an array('tx_batch_id' => array('tx_id'))
   */
  public static function getIdLists($params) {
    // first: extract all the IDs
    if (!empty($params['list'])) {
      $ids = explode(",", $params['list']); 
    } else {
      $ids = array();
    }
    if (!empty($params['s_list'])) {
      $list = CRM_Banking_Page_Payments::getPaymentsForStatements($params['s_list']);
      $ids = array_merge(explode(",", $list), $ids);
    }

    // now create a (sane) SQL query
    $sane_ids = array();
    foreach ($ids as $tx_id) {
      if (is_numeric($tx_id)) {
        $sane_ids[]= (int) $tx_id;
      }
    }
    if (count($sane_ids) == 0) return array();
    $sane_ids_list = implode(',', $sane_ids);

    // query the DB
    $query_sql = "SELECT id, tx_batch_id FROM civicrm_bank_tx WHERE id IN ($sane_ids_list);";
    $result = array();
    $query = CRM_Core_DAO::executeQuery($query_sql);
    while ($query->fetch()) {
      $result[$query->tx_batch_id][] = $query->id;
    }

    return $result;
  }

  /**
   * Will create a temporary file
   *
   * @param params  not yet specified
   *
   * @return a file path to an existing file
   */
  public function getTempFile($params = array()) {
    // generate a uniq temp file
    return tempnam(sys_get_temp_dir(), $this->getPluginID() . '-');
  }

  /**
   * Gather all information on the batch
   * 
   * @return an array containing all values, keys prefixed with 'txbatch_'
   */
  public function getBatchData($tx_batch_id) {
    $result = array();

    // add default values
    $config = $this->_plugin_config;
    if (!empty($config->default_values)) {
      foreach ($config->default_values as $key => $value) {
        $result[$key] = $value;
      }
    }

    $txbatch = civicrm_api3('BankingTransactionBatch', 'getsingle', array('id' => $tx_batch_id));
    if (empty($txbatch['is_error'])) {
      foreach ($txbatch as $key => $value) {
        $result['txbatch_'.$key] = $value;
      }
    } else {
      error_log("org.project60.banking.exporter.csv: error while reading tx_batch [$tx_batch_id]: " . $txbatch['error_message']);
    }
    return $result;
  }

  /**
   * Gather all information on the transaction / payment
   * 
   * @return an array containing all values, keys prefixed with 'tx_'
   */
  public function getTxData($tx_id) {
    $result = array();

    $tx = array();
    $tx_bao = new CRM_Banking_BAO_BankTransaction();
    $tx_bao->get('id', $tx_id);
    CRM_Core_DAO::storeValues($tx_bao, $tx);

    // add all basic fields
    foreach ($tx as $key => $value) {
      $result['tx_'.$key] = $value;
    }

    // resolve status IDs
    $result['tx_status'] = civicrm_api3(
      'OptionValue',
      'getvalue',
      [
        'return' => 'name',
        'option_group_id' => 'civicrm_banking.bank_tx_status',
        'id' => $result['tx_status_id'],
      ]
    );
    $result['tx_status_name'] = civicrm_api3(
      'OptionValue',
      'getvalue',
      [
        'return' => 'label',
        'option_group_id' => 'civicrm_banking.bank_tx_status',
        'id' => $result['tx_status_id'],
      ]
    );

    // add all data_parsed
    $data_parsed = $tx_bao->getDataParsed();
    foreach ($data_parsed as $key => $value) {
      $result['data_'.$key] = $value;
    }
    unset($result['tx_data_parsed']);
    unset($result['tx_suggestions']);

    // add execution info
    $suggestion_objects = $tx_bao->getSuggestionList();
    foreach ($suggestion_objects as $suggestion) {
      if ($suggestion->isExecuted()) {
        $result['exec_date']          = $suggestion->isExecuted();
        $result['exec_executed_by']   = $suggestion->getParameter('executed_by');
        $result['exec_automatically'] = $suggestion->getParameter('executed_automatically');

        // find contribtion IDs
        $contribution_ids = array();
        $suggestion_contribution_id = $suggestion->getParameter('contribution_id');
        if (!empty($suggestion_contribution_id)) {
          if ((int) $suggestion_contribution_id) {
            $contribution_ids[] = (int) $suggestion_contribution_id;
          }
        }
        $suggestion_contribution_ids = $suggestion->getParameter('contribution_ids');
        if (!empty($suggestion_contribution_ids)) {
          if (is_string($suggestion_contribution_ids)) {
            $suggestion_contribution_ids = explode(',', $suggestion_contribution_ids);
          }
          foreach ($suggestion_contribution_ids as $id) {
            $id = (int) $id;
            if ($id) $contribution_ids[] = $id;
          }
        }
        $contribution_ids = array_unique($contribution_ids);
        $result['exec_contribution_count'] = count($contribution_ids);
        $result['exec_contribution_list']  = implode(',', $contribution_ids);

        // also, add individual contribution data
        $counter              = 1;
        $total_sum            = 0.0;
        $total_currency       = '';
        $total_non_deductible = 0.0;
        foreach ($contribution_ids as $contribution_id) {
          $contribution = civicrm_api('Contribution', 'getsingle', array('id' => $contribution_id, 'version' => 3));
          if (!empty($contribtion['is_error'])) {
            error_log("org.project60.banking.exporter.csv: error while reading contribution [$contribution_id]: " . $contribution['error_message']);
          } else {
            $prefix = 'exec_contribution' . (($counter>1)?"_{$counter}_":'_');
            foreach ($contribution as $key => $value) {
              $result[$prefix . $key] = $value;
            }
            if (!empty($contribution['total_amount'])) 
              $total_sum += $contribution['total_amount'];
            if (!empty($contribution['non_deductible_amount'])) 
              $total_non_deductible += $contribution['non_deductible_amount'];
            if (!empty($contribution['currency'])) {
              if (empty($total_currency)) {
                $total_currency = $contribution['currency'];
              } elseif ($total_currency != $contribution['currency']) {
                $total_currency = 'MIX';
              }              
            }
          }
          $counter++;
        }

        $result['exec_total_amount']         = $total_sum;
        $result['exec_total_currency']       = $total_currency;
        $result['exec_total_non_deductible'] = $total_non_deductible;

        break;
      }
    }

    return $result;
  }


  /**
   * standard-method to compile the data blob for the individual line
   * 
   * exporters may override this method to add more information
   *
   * @return the data blob to be used for the next line
   */
  protected function compileDataBlob($tx_batch_data, $tx_data) {
    return array_merge($tx_batch_data, $tx_data);
  }
}

