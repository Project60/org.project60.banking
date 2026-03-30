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

namespace Civi\Banking\Matcher;

use Civi\Api4\BankTransaction;
use Civi\Banking\Matcher\CustomAction\ActionHandlers\TestCustomActionHandler;

/**
 * @covers \Civi\Banking\Matcher\CustomActionsMatcher
 *
 * @group headless
 */
final class CustomActionsMatcherTest extends \CRM_Banking_TestBase {

  public function testExecute(): void {
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
    $matcher = new CustomActionsMatcher($pluginDao);

    $btxValues = BankTransaction::create(FALSE)
      ->addValue('bank_reference', 'test')
      ->addValue('value_date', '2026-03-24 17:30:00')
      ->addValue('booking_date', '2026-03-23 16:00:00')
      ->addValue('amount', -0.1)
      ->addValue('type_id', 1)
      ->addValue('status_id:name', 'new')
      ->addValue('data_raw', '{}')
      ->addValue('data_parsed', '{}')
      ->execute()
      ->single();
    $btxBao = \CRM_Banking_BAO_BankTransaction::findById($btxValues['id']);
    $suggestion = new \CRM_Banking_Matcher_Suggestion($matcher, $btxBao);

    $matcher->execute($suggestion, $btxBao);
    static::assertCount(1, TestCustomActionHandler::getTestCalls());
    static::assertEquals((object) ['type' => 'test', 'foo' => 'bar'], TestCustomActionHandler::getTestCalls()[0][0]);
  }

  public function testMatch(): void {
    $pluginDao = new \CRM_Banking_DAO_PluginInstance();
    $pluginDao->config = <<<EOD
    {
      "title": "test",
      "probability": 0.12,
      "required_values": {
        "btx.amount": "type:negative"
      }
    }
    EOD;
    $matcher = new CustomActionsMatcher($pluginDao);

    $btx = new \CRM_Banking_BAO_BankTransaction();
    $btx->bank_reference = 'test';
    $btx->value_date = '2026-03-24 17:30:00';
    $btx->booking_date = '2026-03-23 16:00:00';
    $btx->amount = -1.1;
    $btx->type_id = 1;
    $btx->status_id = 1;
    $btx->data_raw = '{}';
    $btx->data_parsed = '{}';
    $context = new \CRM_Banking_Matcher_Context($btx);

    $suggestions = $matcher->match($btx, $context);
    static::assertNotNull($suggestions);
    static::assertCount(1, $suggestions);
    static::assertSame('test', $suggestions[0]->getTitle());
    static::assertSame(0.12, $suggestions[0]->getProbability());
    static::assertSame($matcher->getPluginID(), $suggestions[0]->getPluginID());
  }

  public function testMatchDefaultConfig(): void {
    $pluginDao = new \CRM_Banking_DAO_PluginInstance();
    $pluginDao->config = '{}';
    $matcher = new CustomActionsMatcher($pluginDao);

    $btx = new \CRM_Banking_BAO_BankTransaction();
    $btx->bank_reference = 'test';
    $btx->value_date = '2026-03-24 17:30:00';
    $btx->booking_date = '2026-03-23 16:00:00';
    $btx->amount = -1.1;
    $btx->type_id = 1;
    $btx->status_id = 1;
    $btx->data_raw = '{}';
    $btx->data_parsed = '{}';
    $context = new \CRM_Banking_Matcher_Context($btx);

    $suggestions = $matcher->match($btx, $context);
    static::assertNotNull($suggestions);
    static::assertCount(1, $suggestions);
    static::assertNull($suggestions[0]->getTitle());
    static::assertSame(0.8, $suggestions[0]->getProbability());
    static::assertSame($matcher->getPluginID(), $suggestions[0]->getPluginID());
  }

  public function testMatchRequiredValuesNotMatched(): void {
    $pluginDao = new \CRM_Banking_DAO_PluginInstance();
    $pluginDao->config = <<<EOD
    {
      "required_values": {
        "btx.amount": "type:negative"
      }
    }
    EOD;
    $matcher = new CustomActionsMatcher($pluginDao);

    $btx = new \CRM_Banking_BAO_BankTransaction();
    $btx->bank_reference = 'test';
    $btx->value_date = '2026-03-24 17:30:00';
    $btx->booking_date = '2026-03-23 16:00:00';
    $btx->amount = 0;
    $btx->type_id = 1;
    $btx->status_id = 1;
    $btx->data_raw = '{}';
    $btx->data_parsed = '{}';
    $context = new \CRM_Banking_Matcher_Context($btx);

    $suggestions = $matcher->match($btx, $context);
    static::assertNotNull($suggestions);
    static::assertCount(0, $suggestions);
  }

}
