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

use Civi\Api4\BankTransaction;
use Civi\Banking\PostProcessor\CustomAction\ActionHandlers\TestCustomActionHandler;

/**
 * @covers \Civi\Banking\PostProcessor\CustomActionsPostProcessor
 *
 * @group headless
 */
final class CustomActionsPostProcessorTest extends \CRM_Banking_TestBase {

  protected function tearDown(): void {
    parent::tearDown();
    TestCustomActionHandler::clearTestCalls();
  }

  public function testProcessExecutedMatch(): void {
    $pluginDao = new \CRM_Banking_DAO_PluginInstance();
    $pluginDao->config = <<<EOD
    {
      "actions": [
        {
          "type": "test",
          "foo": "bar"
        }
      ]
    }
    EOD;
    $postProcessor = new CustomActionsPostProcessor($pluginDao);

    $btxValues = BankTransaction::create(FALSE)
      ->addValue('bank_reference', 'test')
      ->addValue('value_date', '2026-03-24 17:30:00')
      ->addValue('booking_date', '2026-03-23 16:00:00')
      ->addValue('amount', -0.1)
      ->addValue('type_id', 1)
      ->addValue('status_id:name', 'processed')
      ->addValue('data_raw', '{}')
      ->addValue('data_parsed', '{}')
      ->execute()
      ->single();
    $btxBao = \CRM_Banking_BAO_BankTransaction::findById($btxValues['id']);

    $matcherMock = $this->createMock(\CRM_Banking_PluginModel_Matcher::class);
    $match = new \CRM_Banking_Matcher_Suggestion($matcherMock, $btxBao);
    $matcherContext = new \CRM_Banking_Matcher_Context($btxBao);

    $postProcessor->processExecutedMatch($match, $matcherMock, $matcherContext);
    static::assertCount(1, TestCustomActionHandler::getTestCalls());
    static::assertEquals((object) ['type' => 'test', 'foo' => 'bar'], TestCustomActionHandler::getTestCalls()[0][0]);
  }

}
