<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Banking\Matcher;

use Civi\Banking\Matcher\CustomAction\CustomActionContext;
use Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface;

/**
 * This matcher allows to execute custom actions.
 */
class CustomActionsMatcher extends \CRM_Banking_PluginModel_Matcher {

  public const NAME = 'matcher_custom_actions';

  /**
   * @param \CRM_Banking_DAO_PluginInstance $pluginDao
   */
  public function __construct($pluginDao) {
    parent::__construct($pluginDao);

    $config = $this->getConfig();
    $config->actions ??= [];
    $config->probability ??= 0.8;
  }

  public function match(\CRM_Banking_BAO_BankTransaction $btx, \CRM_Banking_Matcher_Context $context): ?array {
    if (!$this->requiredValuesPresent($btx)) {
      return [];
    }

    $config = $this->getConfig();
    $suggestion = new \CRM_Banking_Matcher_Suggestion($this, $btx);
    if (isset($config->title)) {
      $suggestion->setTitle($config->title);
    }

    $suggestion->setId($this->getPluginID());
    $suggestion->setProbability($config->probability);

    $btx->addSuggestion($suggestion);

    return [$suggestion];
  }

  /**
   * Execute the previously generated suggestion,
   *   and close the transaction
   *
   * @param \CRM_Banking_Matcher_Suggestion $suggestion
   *   the suggestion to be executed
   *
   * @param \CRM_Banking_BAO_BankTransaction $btx
   *   the bank transaction this is related to
   */
  public function execute($suggestion, $btx): bool {
    $config = $this->getConfig();

    /** @var \Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface $actionHandler */
    $actionHandler = \Civi::service(CustomActionHandlerInterface::class);
    $context = new CustomActionContext($this, $btx, $suggestion);
    foreach ($config->actions as $action) {
      $actionHandler->execute($action, $context);
    }

    $newStatus = \CRM_Banking_Helpers_OptionValue::banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);

    return TRUE;
  }

  /**
   * Generate html code to visualize the given suggestion. The visualization may also provide interactive form elements.
   *
   * @param \CRM_Banking_BAO_BankTransaction $btx the bank transaction the suggestion refers to
   * @return string html code snippet
   */
  public function visualize_match(\CRM_Banking_Matcher_Suggestion $suggestion, $btx) {
    return parent::visualize_match($suggestion, $btx);

    $smartyVars = [];

    // assign to smarty and compile HTML
    $smarty = \CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smartyVars);
    try {
      return $smarty->fetch('Civi/Banking/Matcher/CustomActions.suggestion.tpl');
    }
    finally {
      $smarty->popScope();
    }
  }

  /**
   * Generate html code to visualize the executed suggestion.
   *
   * @param \CRM_Banking_BAO_BankTransaction $btx the bank transaction the suggestion refers to
   * @return string html code snippet
   */
  public function visualize_execution_info(\CRM_Banking_Matcher_Suggestion $suggestion, $btx) {
    return parent::visualize_execution_info($suggestion, $btx);

    $smartyVars = [];

    $smarty = \CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smartyVars);
    try {
      return $smarty->fetch('Civi/Banking/Matcher/CustomActions.execution.tpl');
    }
    finally {
      $smarty->popScope();
    }
  }

}
