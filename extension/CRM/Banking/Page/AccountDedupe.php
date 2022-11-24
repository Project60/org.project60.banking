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


use CRM_Banking_ExtensionUtil as E;

require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * The Dedupe page helps the user to identify and merge/delete duplicate
 * bank accounts.
 */
class CRM_Banking_Page_AccountDedupe extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(E::ts('Dedupe Bank Accounts'));

    // execute requested pre-search actions
    $this->executeRequestsPre();

    // scan for duplicates
    $duplicates = $this->findReferences();

    // execute the requested actions
    if ($this->executeRequests($duplicates)) {
      // execution successfull => something has changed => scan again:
      $duplicates = $this->findReferences();
    }

    $this->assign('duplicate_references',       $duplicates['reference']);
    $this->assign('duplicate_references_count', count($duplicates['reference']));
    $this->assign('duplicate_accounts',         $duplicates['account']);
    $this->assign('duplicate_accounts_count',   count($duplicates['account']));
    $this->assign('account_conflicts',          $duplicates['conflict']);
    $this->assign('account_conflicts_count',    count($duplicates['conflict']));

    parent::run();
  }

  /**
   * will scan the database for reference duplicates
   * and returns the findings in three lists
   */
  function findReferences() {
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
                        MIN(civicrm_bank_account_reference.id) AS reference_id,
                        COUNT(civicrm_bank_account_reference.id) AS dupe_count,
                        COUNT(DISTINCT(ba_id)) AS ba_count,
                        COUNT(DISTINCT(contact_id)) AS contact_count,
                        MAX(modified_date) AS last_change,
                        reference,
                        reference_type_id
                      FROM civicrm_bank_account_reference
                      LEFT JOIN civicrm_bank_account ON ba_id = civicrm_bank_account.id
                      WHERE contact_id IS NOT NULL
                      GROUP BY reference, reference_type_id
                      ORDER BY last_change DESC
                      ) AS dupequery
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

    // filter conflicts
    $account_conflicts_filtered = array();
    foreach ($account_conflicts as $account_conflict) {
      if (empty($account_conflict['exception'])) {
        $account_conflicts_filtered[] = $account_conflict;
      }
    }

    return array(
      'reference' => $duplicate_references,
      'account'   => $duplicate_accounts,
      'conflict'  => $account_conflicts_filtered);
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
    } elseif (count($contacts) > 1) {
      // check if it's a known duplicate
      $dupe_exception_clauses = array();
      for ($i = 0; $i < count($contacts); $i++) {
        for ($j = $i + 1; $j < count($contacts); $j++) {
          $contactA_id = $contacts[$i]['id'];
          $contactB_id = $contacts[$j]['id'];
          $dupe_exception_clauses[] = "((contact_id1 = {$contactA_id} AND contact_id2 = {$contactB_id}) OR (contact_id2 = {$contactA_id} AND contact_id1 = {$contactB_id}))";
        }
      }

      $match_count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_dedupe_exception WHERE " . implode(' OR ', $dupe_exception_clauses));
      if ($match_count >= count($dupe_exception_clauses)) {
        // all contacts are mutual dedupes
        $duplicate['exception'] = 1;
      } else {
        // add exclude link
        $exclued_ids = array();
        foreach ($contacts as $contact) {
          $exclued_ids[] = $contact['id'];
        }
        $exclued_ids_list = implode(',', $exclued_ids);
        $duplicate['exclude_link'] = CRM_Utils_System::url('civicrm/banking/dedupe', "reset=1&exclude={$exclued_ids_list}");
      }

      // add merge link if it's exactly two
      if (count($contacts) == 2) {
        $duplicate['merge_link'] = CRM_Utils_System::url('civicrm/contact/merge', "reset=1&cid={$contacts[0]['id']}&oid={$contacts[1]['id']}");
      }
    }
  }

  /**
   * excecute some requestet pre-processing
   */
  function executeRequestsPre() {
    // ==========================
    //  ADD DUPLICATE EXCEPTIONS
    // ==========================
    if (!empty($_REQUEST['exclude'])) {
      $exclued_ids = explode(',', $_REQUEST['exclude']);
      for ($i = 0; $i < count($exclued_ids); $i++) {
        $contact_id1 = (int) $exclued_ids[$i];
        if (!empty($contact_id1)) {
          for ($j = ($i + 1); $j < count($exclued_ids); $j++) {
            $contact_id2 = (int) $exclued_ids[$j];
            if (!empty($contact_id2)) {
              if ($contact_id1 < $contact_id2) {
                CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_dedupe_exception (contact_id1, contact_id2) VALUES ({$contact_id1}, {$contact_id2});");
              } elseif ($contact_id1 > $contact_id2) {
                CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_dedupe_exception (contact_id1, contact_id2) VALUES ({$contact_id2}, {$contact_id1});");
              }
            }
          }
        }
      }
    }
  }

  /**
   * Will execute any dedupe/merge requests specified via the REQUEST params
   */
  function executeRequests($duplicates) {
    $refs_fixed = 0;
    $accounts_fixed = 0;
    $errors_ecountered = 0;
    $account_reflist = array();
    $reflist = array();
    $lenient_dedupe    = CRM_Banking_Config::lenientDedupe();

    // =========================
    // MERGE DUPLICATE ACCOUNTS
    // =========================
    if (!empty($_REQUEST['fixdupe'])) {
      if ($_REQUEST['fixdupe']=='all') {
        foreach ($duplicates['account'] as $reference => $info) {
          if ((int) $info['reference_id'])
            $account_reflist[] = (int) $info['reference_id'];
        }
      } else {
        $parm_list = explode(',', $_REQUEST['fixdupe']);
        foreach ($parm_list as $reference_id) {
          if ((int) $reference_id)
            $account_reflist[] = (int) $reference_id;
        }
      }

      // perform the changes
      foreach ($account_reflist as $reference_id) {
        // MERGE ACCOUNTS

        // first, find the account IDs to merge
        $bank_account_ids = array();
        $sql = "SELECT ba_id
                FROM civicrm_bank_account_reference
                WHERE reference = (SELECT reference FROM civicrm_bank_account_reference WHERE id=$reference_id)
                ORDER BY ba_id ASC;";  // use the oldest (lowest ID) as merge target
        $bank_account_query = CRM_Core_DAO::executeQuery($sql);
        while ($bank_account_query->fetch()) {
          $bank_account_ids[] = $bank_account_query->ba_id;
        }
        if (count($bank_account_ids) < 2) {
          // we need at least two bank accounts to merge...
          $errors_ecountered += 1;
          continue;
        }

        // now: load the first bank account...
        $main_ba = new CRM_Banking_BAO_BankAccount();
        $main_ba->get('id', $bank_account_ids[0]);
        $main_data_parsed = $main_ba->getDataParsed();

        // ...and try to merge the others into it
        $merge_failed = FALSE;
        for ($i=1; $i<count($bank_account_ids); $i++) {
          $merge_ba = new CRM_Banking_BAO_BankAccount();
          $merge_ba->get('id', $bank_account_ids[$i]);

          // merge created_date
          if (isset($merge_ba->created_date) && $merge_ba->created_date < $main_ba->created_date) {
            $main_ba->created_date = $merge_ba->created_date;
          }


          // merge description/data_raw
          $replace_attributes = array('description', 'data_raw');
          foreach ($replace_attributes as $attribute) {
            if (!empty($merge_ba->$attribute)) {
              if (empty($main_ba->$attribute)) {
                // main_ba.$attribute not set, just overwrite
                $main_ba->$attribute = $merge_ba->$attribute;
              } else {
                // main_ba.$attribute set, check if identical
                if ($main_ba->$attribute != $merge_ba->$attribute) {
                  if (!$lenient_dedupe) {
                    $merge_failed = TRUE;
                    break;
                  }
                }
              }
            }
          }
          if ($merge_failed) break;

          // merge data_parsed
          $merge_data_parsed = $merge_ba->getDataParsed();
          foreach ($merge_data_parsed as $key => $value) {
            if (empty($main_data_parsed[$key])) {
              $main_data_parsed[$key] = $value;
            } else {
              if ($main_data_parsed[$key] != $merge_data_parsed[$key]) {
                if (!$lenient_dedupe) {
                  $merge_failed = TRUE;
                  break;
                }
              }
            }
          }
          if ($merge_failed) break;
        } // MERGE NEXT ACCOUNT (for same target)

        if ($merge_failed) {
          $errors_ecountered += 1;
        } else {
          // SAVE THE MERGED OBJECT
          $main_ba->setDataParsed($main_data_parsed);
          $main_ba->modified_date = date('YmdHis');
          $main_ba->save();

          // DELETE THE OTHER, NOW OBSOLETE ACCOUNTS
          $target_id = $bank_account_ids[0];
          unset($bank_account_ids[0]);
          $delete_ids = implode(',', $bank_account_ids);
          CRM_Core_DAO::singleValueQuery("UPDATE civicrm_bank_account_reference SET ba_id=$target_id WHERE ba_id IN ($delete_ids);");
          CRM_Core_DAO::singleValueQuery("UPDATE civicrm_bank_tx SET ba_id=$target_id WHERE ba_id IN ($delete_ids);");
          CRM_Core_DAO::singleValueQuery("UPDATE civicrm_bank_tx SET party_ba_id=$target_id WHERE party_ba_id IN ($delete_ids);");
          CRM_Core_DAO::singleValueQuery("DELETE FROM civicrm_bank_account WHERE id IN ($delete_ids);");
          $accounts_fixed += 1;

          // finally, add the reference_ids to duplicate reference list,
          //  in order to delete the resulting duplicate references
          $reflist[] = $reference_id;
        }
      }

      if ($errors_ecountered) {
        CRM_Core_Session::setStatus(E::ts("%1 errors were encountered when trying to merge duplicate bank accounts, %2/%3 bank accounts were successfully merged.",
              array(1 => $errors_ecountered, 2 => $accounts_fixed, 3 => ($errors_ecountered+$accounts_fixed))), E::ts('Errors encountered'), 'warn');
        $errors_ecountered = 0;
      } else {
        CRM_Core_Session::setStatus(E::ts("%1 duplicate bank accounts successfully merged.", array(1 => $accounts_fixed)), E::ts('Success'), 'info');
      }
    }

    // ============================
    // DELETE DUPLICATE REFERENCES.
    // ============================
    if (!empty($_REQUEST['fixref']) || !empty($reflist)) {
      //  They should be identical and can be safely removed
      if (!empty($_REQUEST['fixref'])) {
        if ($_REQUEST['fixref']=='all') {
          foreach ($duplicates['reference'] as $reference => $info) {
            if ((int) $info['reference_id'])
              $reflist[] = (int) $info['reference_id'];
          }
        } else {
          $parm_list = explode(',', $_REQUEST['fixref']);
          foreach ($parm_list as $reference_id) {
            if ((int) $reference_id)
              $reflist[] = (int) $reference_id;
          }
        }
      }

      // perform the changes
      foreach ($reflist as $reference_id) {
        $sql = "SELECT ref_delete.id AS reference_id
                FROM civicrm_bank_account_reference ref_delete
                LEFT JOIN civicrm_bank_account_reference ref_keep ON ref_keep.id=$reference_id
                WHERE ref_keep.reference = ref_delete.reference
                  AND ref_keep.ba_id = ref_delete.ba_id
                  AND ref_keep.reference_type_id = ref_delete.reference_type_id
                  AND ref_keep.id != ref_delete.id;";
        $reference_to_delete = CRM_Core_DAO::executeQuery($sql);
        while ($reference_to_delete->fetch()) {
          $result = civicrm_api('BankingAccountReference', 'delete', array('id'=>$reference_to_delete->reference_id, 'version'=>3));
          if (empty($result['is_error'])) {
            $refs_fixed += 1;
          } else {
            CRM_Core_Error::debug_log_message("org.project60.banking.dedupe: Error while deleting dupe reference: " .$result['error_message']);
            $errors_ecountered += 1;
          }
        }
      }

      if ($errors_ecountered) {
        CRM_Core_Session::setStatus(E::ts("%1 errors were encountered when trying to delete duplicate references. %2/%3 references were successfully deleted.",
          array(1 => $errors_ecountered, 2 => $refs_fixed, 3 => ($errors_ecountered+$refs_fixed))), E::ts('Errors encountered'), 'warn');
        $errors_ecountered = 0;
      } else {
        CRM_Core_Session::setStatus(E::ts("%1 duplicate references successfully deleted.", array(1=>$refs_fixed)), E::ts('Success'), 'info');
      }
    }


    return $refs_fixed + $accounts_fixed;
  }
}
