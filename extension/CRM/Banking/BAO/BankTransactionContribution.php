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

use CRM_Banking_ExtensionUtil as E;

/**
 * Class contains functions for CiviBanking plugin instances
 */
class CRM_Banking_BAO_BankTransactionContribution extends CRM_Banking_DAO_BankTransactionContribution {

  /**
   * @param array  $params
   *  (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Banking_DAO_BankTransactionContribution object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'BankTransactionContribution', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Banking_DAO_BankTransactionContribution();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'BankTransactionContribution', $dao->id, $dao);
    return $dao;
  }

  /**
   * Link the given contribution to the given bank transaction
   *
   * @param integer $bank_tx_id
   * @param integer $contribution_id
   */
  public static function linkContribution($bank_tx_id, $contribution_id)
  {
    // don't check whether already linked, let the DB do that...
    CRM_Core_DAO::executeQuery(
        "INSERT IGNORE INTO civicrm_bank_tx_contribution (bank_tx_id,contribution_id) VALUES (%1, %2);",
        [
          1 => [$bank_tx_id, 'Integer'],
          2 => [$contribution_id, 'Integer'],
        ]
    );
  }

  /**
   * Get the list of transactions linked to the given contribution
   *
   * @param integer $contribution_id
   *   the contribution ID we're looking up
   *
   * @return array
   *   list of bank transaction IDs
   */
  public static function getLinkedTransactions($contribution_id)
  {
    $tx_ids = [];
    $contribution_id = (int) $contribution_id;
    $linked_btx = CRM_Core_DAO::executeQuery(
        "SELECT bank_tx_id FROM civicrm_bank_tx_contribution WHERE contribution_id = %1;",
        [1 => [$contribution_id, 'Integer']]
    );
    while ($linked_btx->fetch()) {
      $tx_ids[] = (int) $linked_btx->bank_tx_id;
    }
    return $tx_ids;
  }

  /**
   * Inject banking transactions linked to this contribution
   *
   * @param CRM_Contribute_Page_Tab $page
   */
  public static function injectLinkedTransactions($page)
  {
    if (!empty($page->_id) && ($page instanceof CRM_Contribute_Page_Tab)) {
      try {
        $contribution_id = (int) $page->_id;
        $link_title = E::ts("CiviBanking Transaction");
        $tx_ids = self::getLinkedTransactions($contribution_id);
        if (!empty($tx_ids)) {
          // generate links
          $tx_links = [];
          foreach ($tx_ids as $tx_id) {
            $tx_url = CRM_Utils_System::url('civicrm/banking/review',  "id={$tx_id}");
            $tx_links[] = "<a href=\"{$tx_url}\" title=\"{$link_title}\" class=\"crm-popup\">[{$tx_id}]</a>";
          }

          // inject tx_ids:
          Civi::resources()->addVars('contribution_transactions', [
            'label'         => E::ts("%1 Bank Transaction(s)", [1 => count($tx_ids)]),
            'links'         => implode(' ', $tx_links),
          ]);
          Civi::resources()->addScriptUrl(E::url('js/contribution_transaction_snippet.js'));
        }
      } catch (Exception $ex) {
        Civi::log()->debug("Error while checking for linked bank transactions: " . $ex->getMessage());
      }
    }
  }
}

