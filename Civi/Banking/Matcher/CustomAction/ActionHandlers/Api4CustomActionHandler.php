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

namespace Civi\Banking\Matcher\CustomAction\ActionHandlers;

use Civi\Banking\Matcher\CustomAction\CustomActionContext;
use Civi\Banking\Matcher\CustomAction\CustomActionHandlerInterface;
use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Webmozart\Assert\Assert;

/**
 * @implements CustomActionHandlerInterface<object{
 *    entity: string,
 *    action: string,
 *    params?: \stdClass,
 *    result_map?: \stdClass
 *  }>
 */
final class Api4CustomActionHandler implements CustomActionHandlerInterface {

  public const TYPE = 'api4';

  private Api4ParamsFactory $paramFactory;

  private Api4ResultMapper $resultMapper;

  public function __construct(Api4ParamsFactory $paramFactory, Api4ResultMapper $resultMapper) {
    $this->paramFactory = $paramFactory;
    $this->resultMapper = $resultMapper;
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
      fn(string $key) => $context->getValue($key),
      ['btx', 'ba', 'party_ba', 'suggestion']
    );

    $result = civicrm_api4($entityName, $actionName, $params);
    if (0 === $result->countFetched()) {
      return;
    }

    if (property_exists($action, 'result_map')) {
      Assert::isInstanceOf($action->result_map, \stdClass::class);
      $this->resultMapper->mapResult($result, (array) $action->result_map, fn ($key, $value) => $context->setValue($key, $value));
    }
  }

}
