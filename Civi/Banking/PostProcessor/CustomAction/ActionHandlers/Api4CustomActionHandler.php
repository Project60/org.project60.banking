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

namespace Civi\Banking\PostProcessor\CustomAction\ActionHandlers;

use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapOptions;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Civi\Banking\Matcher\Helper\ExpressionLanguageValuesGenerator;
use Civi\Banking\PostProcessor\CustomAction\CustomActionContext;
use Civi\Banking\PostProcessor\CustomAction\CustomActionHandlerInterface;
use Webmozart\Assert\Assert;

/**
 * @implements CustomActionHandlerInterface<object{
 *    entity: string,
 *    action: string,
 *    params?: \stdClass,
 *  }>
 */
final class Api4CustomActionHandler implements CustomActionHandlerInterface {

  public const TYPE = 'api4';

  /**
   * @var \Closure(string, string, array<string, mixed>): \Civi\Api4\Generic\Result
   */
  private \Closure $api4Callback;

  /**
   * @param null|callable(string, string, array<string, mixed>): \Civi\Api4\Generic\Result $api4Callback
   */
  public function __construct(
    private readonly Api4ParamsFactory $paramFactory,
    private readonly Api4ResultMapper $resultMapper,
    ?callable $api4Callback = NULL
  ) {
    $this->api4Callback = ($api4Callback ?? 'civicrm_api4')(...);
  }

  /**
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   */
  public function execute(\stdClass $action, CustomActionContext $context): void {
    $entityName = $action->entity;
    Assert::notNull($entityName, 'Entity name is missing');
    Assert::string($entityName, 'Entity name has to be a string, got "%s"');
    $actionName = $action->action;
    Assert::notNull($actionName, 'Action name is missing');
    Assert::string($actionName, 'Action name has to be a string, got "%s"');

    $params = $this->paramFactory->createParams(
      $action,
      ExpressionLanguageValuesGenerator::generateValuesForPrefixes(
        ['btx', 'ba', 'party_ba', 'match'],
        fn(string $key) => $context->getValue($key)
      )
    );

    $context->log("Execute APIv4 call $entityName.$actionName with " . json_encode($params));
    /** @throws \CRM_Core_Exception */
    $result = ($this->api4Callback)($entityName, $actionName, $params);

    if (property_exists($action, 'result_map')) {
      Assert::isInstanceOf($action->result_map, \stdClass::class, 'Result map has to be an object, got %s');
      $resultMap = (array) $action->result_map;
      $resultMapOptions = Api4ResultMapOptions::fromObject($action->result_map_options ?? NULL);
      foreach ($this->resultMapper->applyResultMap($result, $resultMap, $resultMapOptions) as $key => $value) {
        $context->setValue($key, $value);
      };
    }
  }

}
