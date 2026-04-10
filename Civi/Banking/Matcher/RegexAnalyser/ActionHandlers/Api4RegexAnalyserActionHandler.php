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

namespace Civi\Banking\Matcher\RegexAnalyser\ActionHandlers;

use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Civi\Banking\Matcher\Helper\ExpressionLanguageValuesGenerator;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext;
use Webmozart\Assert\Assert;

final class Api4RegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'api4';

  private Api4ParamsFactory $paramFactory;

  private Api4ResultMapper $resultMapper;

  /**
   * @var \Closure(string, string, array<string, mixed>): \Civi\Api4\Generic\Result
   */
  private \Closure $api4Callback;

  /**
   * @param null|callable(string, string, array<string, mixed>): \Civi\Api4\Generic\Result $api4Callback
   */
  public function __construct(Api4ParamsFactory $paramFactory, Api4ResultMapper $resultMapper, ?callable $api4Callback = NULL) {
    $this->paramFactory = $paramFactory;
    $this->resultMapper = $resultMapper;
    $this->api4Callback = ($api4Callback ?? 'civicrm_api4')(...);
  }

  /**
   * @param \stdClass $action
   * @param \Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext $matchContext
   *
   * @throws \CRM_Core_Exception
   */
  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    $api4 = $action->api4;

    $entityName = $api4->entity ?? NULL;
    Assert::notNull($entityName, 'Entity name is missing');
    Assert::string($entityName, 'Entity name has to be a string, got %s');
    $actionName = $api4->action ?? NULL;
    Assert::notNull($actionName, 'Action name is missing');
    Assert::string($actionName, 'Action name has to be a string, got %s');

    $params = $this->paramFactory->createParams(
      $api4,
      ExpressionLanguageValuesGenerator::generateValuesForPrefixes(
        ['btx', 'ba', 'party_ba'],
        fn(string $key) => $matchContext->getValue($key)
      ) + $matchContext->getMatchedValues()
    );

    /** @throws \CRM_Core_Exception */
    $result = ($this->api4Callback)($entityName, $actionName, $params);

    if (property_exists($api4, 'result_map')) {
      Assert::isInstanceOf($api4->result_map, \stdClass::class, 'Result map has to be an object, got %s');
      $this->resultMapper->mapResult(
        $result,
        (array) $api4->result_map,
        fn($key, $value) => $matchContext->setValue($key, $value)
      );
    }
  }

}
