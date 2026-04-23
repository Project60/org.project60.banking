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

use Civi\Api4\Generic\Result;
use Civi\Banking\ExpressionLanguage\BankingExpressionLanguage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Banking\Matcher\Helper\Api4ResultMapper
 */
final class Api4ResultMapperTest extends TestCase {

  private Api4ResultMapper $resultMapper;

  protected function setUp(): void {
    parent::setUp();
    $this->resultMapper = new Api4ResultMapper(new BankingExpressionLanguage());
  }

  public function testSimple(): void {
    $result = new Result([['foo' => 'bar']]);
    $resultMap = ['btx.field' => 'foo'];
    $options = new Api4ResultMapOptions();

    static::assertSame(
      ['btx.field' => 'bar'],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

  public function testSkipEmptyResult(): void {
    $result = new Result([]);
    $resultMap = ['btx.field' => 'foo'];
    $options = new Api4ResultMapOptions();

    static::assertSame(
      [],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

  public function testDontSkipEmptyResult(): void {
    $result = new Result([]);
    $resultMap = ['btx.field' => 'foo'];
    $options = new Api4ResultMapOptions(skipEmptyResult: FALSE);

    static::assertSame(
      ['btx.field' => NULL],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

  public function testUseAllResults(): void {
    $result = new Result([['foo' => 'bar1'], ['foo' => 'bar2']]);
    $resultMap = ['btx.field' => 'foo'];
    $options = new Api4ResultMapOptions(useAllResults: TRUE);

    static::assertSame(
      ['btx.field' => ['bar1', 'bar2']],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

  public function testIndexBy(): void {
    $result = new Result([['id' => 12, 'foo' => 'bar1'], ['id' => 34, 'foo' => 'bar2']]);
    $resultMap = ['btx.field' => 'foo'];
    $options = new Api4ResultMapOptions(useAllResults: TRUE, indexBy: 'id');

    static::assertSame(
      ['btx.field' => [12 => 'bar1', 34 => 'bar2']],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

  public function testExpression(): void {
    $result = new Result([['foo' => 'bar']]);
    $resultMap = ['btx.field' => "@=result.single()['foo'] ~ '_test'"];
    $options = new Api4ResultMapOptions();

    static::assertSame(
      ['btx.field' => 'bar_test'],
      [...$this->resultMapper->applyResultMap($result, $resultMap, $options)]
    );
  }

}
