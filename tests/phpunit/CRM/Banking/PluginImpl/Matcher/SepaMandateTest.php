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

/**
 * @covers CRM_Banking_PluginImpl_Matcher_SepaMandate
 *
 * @group headless
 */
final class CRM_Banking_PluginImpl_Matcher_SepaMandateTest extends CRM_Banking_TestBase {

  public function testConstruct(): void {
    $matcher = $this->createSepaMatcher([]);
    // cancellation_date_field defaults to "value_date" for historical reasons.
    static::assertSame('value_date', $matcher->getConfig()->cancellation_date_field);

    $matcher = $this->createSepaMatcher(['cancellation_date_field' => 'booking_date']);
    static::assertSame('booking_date', $matcher->getConfig()->cancellation_date_field);
  }

  /**
   * @param array<string, mixed> $pluginConfig
   */
  private function createSepaMatcher(array $pluginConfig): CRM_Banking_PluginImpl_Matcher_SepaMandate {
    $pluginBao = new CRM_Banking_BAO_PluginInstance();
    $pluginBao->weight = 1;
    $pluginBao->name = 'Test SEPA Matcher';
    if ([] === $pluginConfig) {
      // Ensure object serialization.
      $pluginConfig['___'] = '___';
    }
    // @phpstan-ignore assign.propertyType
    $pluginBao->config = json_encode($pluginConfig);

    return new CRM_Banking_PluginImpl_Matcher_SepaMandate($pluginBao);
  }

}
