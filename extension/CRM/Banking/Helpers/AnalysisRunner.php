<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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

use CRM_Banking_ExtensionUtil as E;

define('CIVIBANKING_RUNNER_JOB_SIZE', 25);

/**
 * This implements a CiviCRM Queue Runner
 *  for bank transaction analysis/matching
 */
class CRM_Banking_Helpers_AnalysisRunner {

  public $title      = NULL;
  protected $btx_ids = NULL;

  protected function __construct($btx_ids, $batch_number) {
    $this->title   = $title;
    $this->btx_ids = $btx_ids;

    if ($btx_ids === NULL) {
      $this->title = E::ts("Initialising analyser...");
    } else {
      $start_index = (($batch_number-1) * CIVIBANKING_RUNNER_JOB_SIZE);
      $this->title = E::ts("Analysing bank transactions %1-%2", array(
            1 => $start_index,
            2 => $start_index + count($this->btx_ids)));
    }
  }

  public function run($context) {
    if ($this->btx_ids === NULL) return TRUE;

    // simply create an engine and match all
    $engine = CRM_Banking_Matcher_Engine::getInstance();
    foreach ($this->btx_ids as $btx_id) {
      $engine->match($btx_id);
    }

    return TRUE;
  }

  /**
   * Use CRM_Queue_Runner to apply the templates
   * This doesn't return, but redirects to the runner
   */
  public static function createRunner($btx_list, $back_url) {
    // create a queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type'  => 'Sql',
      'name'  => 'civibanking_analysis_runner',
      'reset' => TRUE,
    ));

    // create the runner items
    // first: "initialising"
    $queue->createItem(new CRM_Banking_Helpers_AnalysisRunner(NULL, NULL));

    // then: all others
    $current_batch = array();
    $current_batch_number = 1;
    foreach ($btx_list as $btx_id) {
      $current_batch[] = $btx_id;
      if (count($current_batch) == CIVIBANKING_RUNNER_JOB_SIZE) {
        $queue->createItem(new CRM_Banking_Helpers_AnalysisRunner($current_batch, $current_batch_number));
        $current_batch = array();
        $current_batch_number += 1;
      }
    }
    if (!empty($current_batch)) {
      $queue->createItem(new CRM_Banking_Helpers_AnalysisRunner($current_batch, $current_batch_number));
    }

    // create a runner and launch it
    $runner = new CRM_Queue_Runner(array(
      'title'     => E::ts("Analysing %1 bank transactions", array(1 => count($btx_list))),
      'queue'     => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl'  => $back_url,
    ));

    // Initialise runner and return URL
    $_SESSION['queueRunners'][$runner->qrid] = serialize($runner);
    $url = CRM_Utils_System::url($runner->pathPrefix . '/runner', 'reset=1&qrid=' . urlencode($runner->qrid));
    return $url;
  }
}