<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

    
require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';


class CRM_Banking_Page_AccountDedupe extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Dedupe Bank Accounts'));

    // first, execute the requested actions
    $this->executeRequests();

    // look up reference types
    $reference_type_group_id = banking_helper_optiongroupid_by_name('civicrm_banking.reference_types');
    $reference_types = civicrm_api3('OptionValue', 'get', array('option_group_id' => $reference_type_group_id));
    $reference_types = $reference_types['values'];

    // then, identify the duplicates
    $duplicate_references = array();
    $duplicate_accounts   = array();
    $account_conflicts    = array();

    $sql = "SELECT * 
                FROM (SELECT 
                        civicrm_bank_account_reference.id reference_id,
                        COUNT(civicrm_bank_account_reference.id) dupe_count, 
                        COUNT(DISTINCT(ba_id)) ba_count,
                        COUNT(DISTINCT(contact_id)) contact_count,
                        reference,
                        reference_type_id 
                      FROM civicrm_bank_account_reference 
                      LEFT JOIN civicrm_bank_account ON ba_id = civicrm_bank_account.id
                      GROUP BY reference, reference_type_id) AS dupequery 
                WHERE dupequery.dupe_count > 1;";
    $duplicate = CRM_Core_DAO::executeQuery($sql);
    while ($duplicate->fetch()) {
        $info = array(  'reference'         => $duplicate->reference,
                        'reference_id'      => $duplicate->reference_id,
                        'dupe_count'        => $duplicate->dupe_count,
                        'reference_type_id' => $duplicate->reference_type_id,
                        'reference_type'    => $reference_types[$duplicate->reference_type_id]);
        if ($duplicate->ba_count == 1) {
            $duplicate_references[$duplicate->reference] = $info;
        } elseif ($duplicate->contact_count == 1) {
            $duplicate_accounts[$duplicate->reference]   = $info;
        } else {
            $account_conflicts[$duplicate->reference]    = $info;
        }
    }

    // add information
    foreach ($duplicate_references as &$duplicate_reference) $this->addContactInformation($duplicate_reference);
    foreach ($duplicate_accounts   as &$duplicate_account)   $this->addContactInformation($duplicate_account);
    foreach ($account_conflicts    as &$account_conflict)    $this->addContactInformation($account_conflict);
    
    $this->assign('duplicate_references',       $duplicate_references);
    $this->assign('duplicate_references_count', count($duplicate_references));
    $this->assign('duplicate_accounts',         $duplicate_accounts);
    $this->assign('duplicate_accounts_count',   count($duplicate_accounts));
    $this->assign('account_conflicts',          $account_conflicts);
    $this->assign('account_conflicts_count',    count($account_conflicts));
    parent::run();
  }

  /**
   * Will look up and append the contact information of all contacts involved
   */
  function addContactInformation(&$duplicate) {
    // get contact IDs
    $contacts = array();
    $sql = "SELECT DISTINCT(contact_id) AS contact_id
            FROM civicrm_bank_account_reference 
            LEFT JOIN civicrm_bank_account ON ba_id = civicrm_bank_account.id
            WHERE reference = '{$duplicate['reference']}' AND reference_type_id = {$duplicate['reference_type_id']};";
    $contact_query = CRM_Core_DAO::executeQuery($sql);
    while ($contact_query->fetch()) {
        $contact = civicrm_api('Contact', 'getsingle', array('version'=>3, 'id' => $contact_query->contact_id));
        if (empty($contact['is_error'])) {
            $contacts[] = $contact;
        } else {
            // TODO: error handling
        }
    }

    $duplicate['contacts'] = $contacts;
    if (count($contacts) == 1) {
        $duplicate['contact'] = $contacts[0];
    } 
  }

  /**
   * Will execute any dedupe/merge requests specified via the REQUEST params
   */
  function executeRequests() {
    // TODO
  }
}
