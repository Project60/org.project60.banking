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

namespace Civi\Banking\PostProcessor;

use Civi\Banking\PostProcessor\CustomAction\CustomActionContext;
use Civi\Banking\PostProcessor\CustomAction\CustomActionHandlerInterface;
use CRM_Banking_Matcher_Context;
use CRM_Banking_Matcher_Suggestion;
use CRM_Banking_PluginModel_Matcher;

use CRM_Banking_ExtensionUtil as E;

final class CustomActionsPostProcessor extends \CRM_Banking_PluginModel_PostProcessor {

  public const NAME = 'postprocessor_custom_actions';

  public static function description(): string {
    return E::ts('Executes custom actions.');
  }

  public static function title(): string {
    return E::ts('Custom Actions PostProcessor');
  }

  public function __construct(\CRM_Banking_DAO_PluginInstance $pluginDao) {
    parent::__construct($pluginDao);

    $config = $this->getConfig();
    $config->actions ??= [];
  }

  /**
   * @inheritDoc
   */
  public function processExecutedMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context
  ): ?bool {
    if (!$this->shouldExecute($match, $matcher, $context)) {
      return FALSE;
    }

    $config = $this->getConfig();

    /** @var \Civi\Banking\PostProcessor\CustomAction\CustomActionHandlerInterface<object> $actionHandler */
    $actionHandler = \Civi::service(CustomActionHandlerInterface::class);
    $customActionContext = new CustomActionContext($this, $match, $context);
    foreach ($config->actions as $action) {
      $actionHandler->execute($action, $customActionContext);
    }

    return NULL;
  }

}
