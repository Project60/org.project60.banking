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

use Civi\Api4\Generic\Result;
use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @covers \Civi\Banking\Matcher\RegexAnalyser\ActionHandlers\Api4RegexAnalyserActionHandler
 */
final class Api4RegexAnalyserActionHandlerTest extends TestCase {

  private MockObject $api4Mock;

  private Api4RegexAnalyserActionHandler $handler;

  protected function setUp(): void {
    parent::setUp();
    $this->api4Mock = $this->createPartialMock(\stdClass::class, ['execute']);
    $expressionLanguage = new ExpressionLanguage();
    $this->handler = new Api4RegexAnalyserActionHandler(
      new Api4ParamsFactory($expressionLanguage),
      new Api4ResultMapper($expressionLanguage),
      // @phpstan-ignore method.notFound
      fn (...$args) => $this->api4Mock->execute(...$args),
    );
  }

  public function testExecuteNoResult(): void {
    $action = (object) [
      'action' => 'api4',
      'api4' => (object) [
        'entity' => 'SomeEntity',
        'action' => 'get',
        'params' => (object) [
          'where' => [
            ['foo', '=', 'bar'],
          ],
        ],
        'result_map' => (object) [
          'btx.some_value' => 'baz',
        ],
      ],
    ];

    $contextMock = $this->createMock(RegexAnalyserMatchContext::class);

    $this->api4Mock->expects(static::once())
      ->method('execute')
      ->with('SomeEntity', 'get', [
        'where' => [['foo', '=', 'bar']],
        'select' => ['baz'],
      ])
      ->willReturn(new Result([]));

    $contextMock->expects(static::never())->method('setValue');

    $this->handler->execute($action, $contextMock);
  }

  public function testExecuteSingleResult(): void {
    $action = (object) [
      'action' => 'api4',
      'api4' => (object) [
        'entity' => 'SomeEntity',
        'action' => 'get',
        'params' => (object) [
          'where' => [
            ['foo', '=', '@=foo + btx.foo'],
          ],
        ],
        'result_map' => (object) [
          'btx.some_value' => 'bar',
        ],
      ],
    ];

    $contextMock = $this->createMock(RegexAnalyserMatchContext::class);
    $contextMock->method('getMatchedValues')->willReturn(['foo' => '2']);
    $contextMock->method('getValue')->with('btx.foo')->willReturn(3);

    $this->api4Mock->expects(static::once())
      ->method('execute')
      ->with('SomeEntity', 'get', [
        'where' => [['foo', '=', 5]],
        'select' => ['bar'],
      ])
      ->willReturn(new Result([['bar' => 'test1'], ['bar' => 'test2']]));

    $contextMock
      ->expects(static::once())
      ->method('setValue')
      ->with('btx.some_value', 'test1,test2');

    $this->handler->execute($action, $contextMock);
  }

  public function testExecuteMultipleResults(): void {
    $action = (object) [
      'action' => 'api4',
      'api4' => (object) [
        'entity' => 'SomeEntity',
        'action' => 'get',
        'params' => (object) [
          'where' => [
            ['foo', '=', "@=foo + party_ba['foo.foo']"],
          ],
        ],
        'result_map' => (object) [
          'btx.some_value' => 'bar',
        ],
      ],
    ];

    $contextMock = $this->createMock(RegexAnalyserMatchContext::class);
    $contextMock->method('getMatchedValues')->willReturn(['foo' => '2']);
    $contextMock->method('getValue')->with('party_ba.foo.foo')->willReturn(3);

    $this->api4Mock->expects(static::once())
      ->method('execute')
      ->with('SomeEntity', 'get', [
        'where' => [['foo', '=', 5]],
        'select' => ['bar'],
      ])
      ->willReturn(new Result([
        ['bar' => 'test1'],
        ['bar' => 'test2'],
      ]));

    $contextMock
      ->expects(static::once())
      ->method('setValue')
      ->with('btx.some_value', 'test1,test2');

    $this->handler->execute($action, $contextMock);
  }

  public function testExecuteExpression(): void {
    $action = (object) [
      'action' => 'api4',
      'api4' => (object) [
        'entity' => 'SomeEntity',
        'action' => 'get',
        'params' => (object) [
          'where' => [
            ['foo', '=', '@=foo + btx.foo'],
          ],
        ],
        'result_map' => (object) [
          'btx.some_value' => "@=result.first()['bar'][0] ~ result.first()['baz']",
        ],
      ],
    ];

    $contextMock = $this->createMock(RegexAnalyserMatchContext::class);
    $contextMock->method('getMatchedValues')->willReturn(['foo' => '2']);
    $contextMock->method('getValue')->with('btx.foo')->willReturn(3);

    $this->api4Mock->expects(static::once())
      ->method('execute')
      ->with('SomeEntity', 'get', [
        'where' => [['foo', '=', 5]],
        'select' => [],
      ])
      ->willReturn(new Result([['bar' => ['test1', 234], 'baz' => 'test2']]));

    $contextMock
      ->expects(static::once())
      ->method('setValue')
      ->with('btx.some_value', 'test1test2');

    $this->handler->execute($action, $contextMock);
  }

}
