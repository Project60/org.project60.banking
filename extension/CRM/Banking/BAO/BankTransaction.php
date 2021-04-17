<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
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
 * Class contains functions for CiviBanking bank transactions
 */
class CRM_Banking_BAO_BankTransaction extends CRM_Banking_DAO_BankTransaction {

  /**
   * an array of the structure
   * <probability> => array(<CRM_Banking_Matcher_Suggestion>)
   */
  protected $suggestion_objects = array();


  /**
   * public array listing all 'native' data fields, i.e. data DB columns,
   *  all user defined data beyond that will be stored in the data_parsed blob
   */
  public static $native_data_fields = array('amount', 'value_date', 'booking_date', 'currency', 'ba_id', 'party_ba_id');

  /**
   * caches a decoded version of the data_parsed field
   */
  protected $_decoded_data_parsed = NULL;

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Banking_BAO_BankTransaction object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransaction', CRM_Utils_Array::value('id', $params), $params);

    // TODO: convert the arrays (suggestions, data_parsed) back into JSON
    $dao = new CRM_Banking_DAO_BankTransaction();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransaction', $dao->id, $dao);
    return $dao;
  }


  /**
   * Delete function addendum: update statement's count
   *
   * @see https://github.com/Project60/CiviBanking/issues/59
   */
  static function del($ba_id) {
    // get batch (statement) id
    $ba_bao = new CRM_Banking_BAO_BankTransaction();
    $ba_bao->get('id', $ba_id);
    $batch_id = $ba_bao->tx_batch_id;

    // delete the transaction / payments
    $ba_bao->delete();

    // if $batch exists, update count
    if (!empty($batch_id)) {
      $new_count_query = "SELECT COUNT(`id`) FROM `civicrm_bank_tx` WHERE `tx_batch_id`='$batch_id'";
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_bank_tx_batch` SET `tx_count` = ($new_count_query) WHERE `id`='$batch_id';");
    }
  }

  /**
   * Get an CRM_Banking_BAO_BankAccount object representing the target/own bank account
   */
  public function getBankAccount() {
    if ($this->ba_id) {
      $bank_bao = new CRM_Banking_BAO_BankAccount();
      $bank_bao->get('id', $this->ba_id);
      return $bank_bao;
    } else {
      return NULL;
    }
  }

  /**
   * Get an CRM_Banking_BAO_BankAccount object representing the source/party bank account
   */
  public function getPartyBankAccount() {
    if ($this->party_ba_id) {
      $bank_bao = new CRM_Banking_BAO_BankAccount();
      $bank_bao->get('id', $this->party_ba_id);
      return $bank_bao;
    } else {
      return NULL;
    }
  }

  /**
   * an array of the structure
   * <probability> => array(<CRM_Banking_Matcher_Suggestion>)
   *
   * TODO: after a load/retrieve, need to convert the suggestions/data_parsed from JSON to array
   */
  public function getSuggestions() {
    return $this->suggestion_objects;
  }

  /**
   * will provide a cached version of the decoded data_parsed field
   * if $update=true is given, it will be parsed again
   */
  public function getDataParsed($update=false) {
    if ($this->_decoded_data_parsed==NULL || $update) {
      $this->_decoded_data_parsed = json_decode($this->data_parsed, true);
    }
    return $this->_decoded_data_parsed;
  }

  /**
   * store a data parsed structure into the db field.
   */
  public function setDataParsed($data) {
    $this->data_parsed = json_encode($data);
    $sql = "
      UPDATE
        civicrm_bank_tx
      SET
        data_parsed = '" . $this->escape($this->data_parsed) . "'
      WHERE
      id = {$this->id};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $this->getDataParsed(true);
  }

  /**
   * get a suggestion by its hash key
   */
  public function getSuggestionByHash($hash) {
    foreach ($this->suggestion_objects as $probability => $list) {
      foreach ($list as $suggestion) {
        if ($suggestion->getHash() == $hash) {
          return $suggestion;
        }
      }
    }
    return NULL;
  }

  /**
   * get a flat list of CRM_Banking_Matcher_Suggestion
   *
   * @see: getSuggestions()
   */
  public function getSuggestionList() {
    $suggestions = array();
    krsort($this->suggestion_objects);
    foreach ($this->suggestion_objects as $probability => $list) {
      foreach ($list as $item) {
        array_push($suggestions, $item);
      }
    }
    return $suggestions;
  }

  public function resetSuggestions() {
    $this->suggestion_objects = array();
  }

  /**
   * @param CRM_Banking_Matcher_Suggestion $suggestion
   */
  public function addSuggestion($suggestion) {
    // Add notification about post processors to be run on the suggestion.
    $engine = CRM_Banking_Matcher_Engine::getInstance();
    $suggestion->setParameter(
      'post_processor_previews',
      $engine->previewPostProcessors(
        $suggestion,
        $this,
        $suggestion->getPlugin()
      )
    );

    $this->suggestion_objects[floor(100 * $suggestion->getProbability())][] = $suggestion;
  }

  /**
   * Persist suggestiosn for this BTX by converting them into a specific JSON string
   *
   * TODO: fix problem by which a $bao->save() operation screws up the date values
   */
  public function saveSuggestions() {
    $sugs = array();
    krsort($this->suggestion_objects);
    foreach ($this->suggestion_objects as $probability => $list) {
      foreach ($list as $sug) {
        $sugs[] = $sug->prepForJson();
      }
    }
    $this->suggestions = json_encode($sugs);
    $sql = "
      UPDATE civicrm_bank_tx SET
      suggestions = '" . $this->escape($this->suggestions) . "'
      WHERE id = {$this->id}
      ";
    $dao = CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Update this BTX's status. Does not use the $bao>save() technique because of the
   * issue described above.
   *
   * @param type $status_id
   */
  public function setStatus($status_id) {
    $sql = "
      UPDATE civicrm_bank_tx SET
      status_id = $status_id
      WHERE id = {$this->id}
      ";
    CRM_Core_DAO::executeQuery($sql);
    $this->status_id = $status_id;
  }

  /**
   * Upon loading a BTX from database, restore suggestions as they were
   * stored in JSON format
   *
   * TODO: move the restore to an instance method of Suggestion, thus no longer
   * expising the structure of the Suggestion here
   */
  private function restoreSuggestions() {
    if ($this->suggestion_objects == null && $this->suggestions) {
      $sugs = $this->suggestions;
      if ($sugs != '') {
        $sugs = json_decode($sugs, true);
        foreach ($sugs as $sug) {
          $pi_bao = new CRM_Banking_BAO_PluginInstance();
          $pi_bao->get('id', $sug['plugin_id']);
          $s = new CRM_Banking_Matcher_Suggestion($pi_bao->getInstance(), $this, $sug);
          $this->addSuggestion($s);
        }
      }
    }
  }

  public function get($k = NULL, $v = NULL) {
    parent::get($k, $v);
    $this->restoreSuggestions();
  }


  /**
   * Identify the IDs of <n> oldest (by value_date) yet unprocessed bank transactions
   *
   * @param $max_count       the maximal amount of bank transactions to process
   *
   * @return the actual amount of contributions processed
   */
  public static function findUnprocessedIDs($max_count) {
    $results = array();
    $maxcount = (int) $max_count;
    $status_id_new = (int) banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'new');
    $sql_query = "SELECT `id` AS txid FROM `civicrm_bank_tx` WHERE `status_id` = '$status_id_new' ORDER BY `value_date` ASC, `id` ASC LIMIT $maxcount";
    $query_results = CRM_Core_DAO::executeQuery($sql_query);
    while ($query_results->fetch()) {
      $results[] = $query_results->txid;
    }
    return $results;
  }
}

