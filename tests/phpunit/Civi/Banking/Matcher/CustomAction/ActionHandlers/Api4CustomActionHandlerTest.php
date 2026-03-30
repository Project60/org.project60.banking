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

use Civi\Api4\Contact;
use Civi\Banking\Matcher\CustomAction\CustomActionContext;
use Civi\Banking\Matcher\Helper\Api4ParamsFactory;
use Civi\Banking\Matcher\Helper\Api4ResultMapper;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @covers \Civi\Banking\Matcher\CustomAction\ActionHandlers\Api4CustomActionHandler
 *
 * @group headless
 */
final class Api4CustomActionHandlerTest extends \CRM_Banking_TestBase {

  private Api4CustomActionHandler $handler;

  protected function setUp(): void {
    parent::setUp();
    $expressionLanguage = new ExpressionLanguage();
    $this->handler = new Api4CustomActionHandler(
      new Api4ParamsFactory($expressionLanguage),
      new Api4ResultMapper($expressionLanguage)
    );
  }

  public function testExecute(): void {
    $action = (object) [
      'entity' => 'Contact',
      'action' => 'create',
      'params' => (object) [
        'values' => (object) [
          'contact_type' => 'Individual',
          'first_name' => '@=btx.first_name',
        ],
      ],
      'result_map' => (object) [
        'btx.contact_id' => 'id',
      ],
    ];

    $contextMock = $this->createMock(CustomActionContext::class);
    $contextMock->method('getValue')->with('btx.first_name')->willReturn('test');
    $contextMock
      ->expects(static::once())
      ->method('setValue')
      ->willReturnCallback(function (string $key, $value) {
        static::assertSame('btx.contact_id', $key);
        static::assertIsInt($value);
        static::assertSame(
          'test',
          Contact::get(FALSE)->addWhere('id', '=', $value)->execute()->single()['first_name']
        );
      });

    $this->handler->execute($action, $contextMock);
  }

}
