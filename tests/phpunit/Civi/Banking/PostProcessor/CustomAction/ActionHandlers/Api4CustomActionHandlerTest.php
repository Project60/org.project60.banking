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

use Civi\Api4\Generic\Result;
use Civi\Banking\Api4Mock;
use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Civi\Banking\PostProcessor\CustomAction\CustomActionContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Banking\PostProcessor\CustomAction\ActionHandlers\Api4CustomActionHandler
 */
final class Api4CustomActionHandlerTest extends TestCase {

  private Api4Mock&MockObject $api4Mock;

  private Api4CustomActionHandler $handler;

  protected function setUp(): void {
    parent::setUp();
    $this->api4Mock = $this->createMock(Api4Mock::class);
    $expressionLanguage = new BankingExpressionLanguage();
    $this->handler = new Api4CustomActionHandler(
      new Api4ParamsFactory($expressionLanguage),
      new Api4ResultMapper($expressionLanguage),
      $this->api4Mock
    );
  }

  public function testExecute(): void {
    $action = (object) [
      'entity' => 'SomeEntity',
      'action' => 'create',
      'params' => (object) [
        'values' => (object) [
          'foo' => '@=btx.foo',
        ],
      ],
      'result_map' => (object) [
        'tmp.id' => 'id',
      ],
    ];

    $contextMock = $this->createMock(CustomActionContext::class);
    $contextMock->method('getValue')->with('btx.foo')->willReturn('bar');

    $this->api4Mock->expects(static::once())
      ->method('__invoke')
      ->with('SomeEntity', 'create', [
        'values' => [
          'foo' => 'bar',
        ],
      ])
      ->willReturn(new Result([['id' => 123]]));

    $contextMock->expects(static::once())->method('setValue')->with('tmp.id', 123);

    $this->handler->execute($action, $contextMock);
  }

}
