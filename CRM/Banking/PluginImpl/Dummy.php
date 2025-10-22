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

declare(strict_types = 1);

// phpcs:disable Squiz.PHP.CommentedOutCode.Found
// Example config file:
// {
//   "amounts":  [ "35.00", "(rand(0,20000)-10000)/100" ],
//   "purposes": [ "membership", "donation", "buy yourself something nice" ]
// }
// phpcs:enable

class CRM_Banking_PluginImpl_Dummy extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */
  public function __construct($config_name) {
    parent::__construct($config_name);
  }

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  public static function displayName() {
    return 'Dummy Importer';
  }

  /**
   * Report if the plugin is capable of importing files
   *
   * @return bool
   */
  public static function does_import_files() {
    return FALSE;
  }

  /**
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   *
   * @return bool
   */
  public static function does_import_stream() {
    return TRUE;
  }

  /**
   * Test if the given file can be imported
   *
   * @var
   * @return TODO: data format?
   */
  public function probe_file($file_path, $params) {
    return FALSE;
  }

  /**
   * Import the given file
   *
   * @return TODO: data format?
   */
  public function import_file($file_path, $params) {
    $this->reportDone([]);
    return FALSE;
  }

  /**
   * Test if the configured source is available and ready
   *
   * @var
   * @return TODO: data format?
   */
  public function probe_stream($params) {
    return TRUE;
  }

  /**
   * Import the given file
   *
   * @return TODO: data format?
   */
  public function import_stream($params) {
    $config = $this->_plugin_config;
    $count = rand(5, 10);
    $this->reportProgress(0.0, 'Creating ' . $count . ' fake bank transactions.');

    // fetch $count random contacts
    $query_params = [
      'version' => 3,
      'option.sort' => 'hash',
      'option.limit' => 2 * $count,
    ];
    $result = civicrm_api('Contact', 'get', $query_params);
    if ($result['is_error']) {
      $this->reportDone('Error while fetching contacts.');
      return;
    }
    $contacts = $result['values'];

    // set up gibberish, purposes, reference number
    $reference = rand(1000, 10000);
    $gibberish = explode(' ', 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.');
    if (isset($config->purposes)) {
      $purposes = $config->purposes;
    }
    else {
      $purposes = ['membership', 'donation', 'buy yourself something nice', 'spende', 'birthday', 'campaign sadsca', '2013/' . rand(1000, 10000)];
    }

    // set up amounts
    if (isset($config->amounts)) {
      $amounts = $config->amounts;
    }
    else {
      $amounts = ['(rand(0,20000)-10000)/100'];
    }

    // create batch
    $batch = $this->openTransactionBatch();

    // now create <$count> entries
    for ($i = 1; $i <= $count; $i++) {
      // pick a contact to work with
      $contact = $contacts[array_rand($contacts)];

      $timestamp = rand(strtotime('-14 days'), strtotime('now'));

      // create the data blobs
      shuffle($gibberish);
      $mygibberish = implode(' ', array_slice($gibberish, 0, rand(0, 5))) . "\n"
                          . $contact['display_name'] . "\n"
                          . implode(' ', array_slice($gibberish, 0, rand(0, 5)));
      $data_parsed = [
        'name' => $contact['display_name'],
        'purpose' => $purposes[array_rand($purposes)],
      ];

      // create the amount
      $amount_selection = $amounts[array_rand($amounts)];
      // I know...sorry about eval(). !DO NOT USE THIS IN REAL IMPLEMENTATION!
      // phpcs:disable Drupal.Functions.DiscouragedFunctions.Discouraged
      $amount = eval('return ' . $amount_selection . ';');
      // phpcs:enable

      // generate entry data
      $btx = [
        'version' => 3,
        'debug' => 1,
      // taken from config
        'amount' => $amount,
        'bank_reference' => $reference . '-' . $i,
      // last two weeks
        'value_date' => date('YmdHis', $timestamp),
      // last two weeks (do we want an offset?)
        'booking_date' => date('YmdHis', $timestamp),
      // EUR
        'currency' => 'EUR',
      // TODO: lookup type ?
        'type_id' => 0,
      // TODO: lookup status new
        'status_id' => 0,
      // gibberish + name
        'data_raw' => $mygibberish,
      // name, purpose
        'data_parsed' => json_encode($data_parsed),
      // TODO: config
        'ba_id' => '',
      // TODO: config
        'party_ba_id' => '',
      // TODO: create batch
        'tx_batch_id' => NULL,
      // sequence number
        'sequence' => $i,
      ];

      // and finally write it into the DB
      $duplicate = $this->checkAndStoreBTX($btx, ($i / $count), $params);
      // TODO: process duplicates or failures?
    }

    //TODO: customize batch params

    $batch = $this->closeTransactionBatch();

    $this->reportDone();
  }

}
