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

namespace Civi\Banking\Matcher\Helper;

use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Banking\Matcher\Helper\Api4ParamsFactory
 */
final class Api4ParamsFactoryTest extends TestCase {

  private Api4ParamsFactory $paramsFactory;

  protected function setUp(): void {
    parent::setUp();
    $this->paramsFactory = new Api4ParamsFactory(new BankingExpressionLanguage());
  }

  public function testDefault(): void {
    $actionDefinition = (object) [
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
    ];

    static::assertEquals([
      'where' => [['foo', '=', 'bar']],
      'select' => ['baz'],
      'limit' => 1,
    ], $this->paramsFactory->createParams($actionDefinition));
  }

  public function testNotGet(): void {
    $actionDefinition = (object) [
      'entity' => 'SomeEntity',
      'action' => 'someAction',
      'params' => (object) [
        'foo' => 'bar',
      ],
      'result_map' => (object) [
        'btx.some_value' => 'baz',
      ],
    ];

    // 'select' and 'limit' are not set.
    static::assertEquals([
      'foo' => 'bar',
    ], $this->paramsFactory->createParams($actionDefinition));
  }

  public function testWithExpression(): void {
    $actionDefinition = (object) [
      'entity' => 'SomeEntity',
      'action' => 'get',
      'result_map' => (object) [
        'btx.some_value' => 'baz',
        'btx.another_value' => "@=result.first()['test']",
      ],
    ];

    static::assertEquals([
      // 'limit' is not set with expression.
      // 'select' has to be empty.
      'select' => [],
    ], $this->paramsFactory->createParams($actionDefinition));
  }

  public function testUseAllResults(): void {
    $actionDefinition = (object) [
      'entity' => 'SomeEntity',
      'action' => 'get',
      'use_all_results' => TRUE,
      'result_map' => (object) [
        'btx.some_value' => 'baz',
      ],
    ];

    // 'limit' is not set.
    static::assertEquals([
      'select' => ['baz'],
    ], $this->paramsFactory->createParams($actionDefinition));
  }

  public function testIndexBy(): void {
    $actionDefinition = (object) [
      'entity' => 'SomeEntity',
      'action' => 'get',
      'use_all_results' => TRUE,
      'index_by' => 'bar',
      'result_map' => (object) [
        'btx.some_value' => 'baz',
      ],
    ];

    // index_by field is in 'select'.
    static::assertEquals([
      'select' => ['bar', 'baz'],
    ], $this->paramsFactory->createParams($actionDefinition));
  }

}
