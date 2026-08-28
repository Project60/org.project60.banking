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
 * Test the bank transaction BAO
 *
 * @covers CRM_Banking_BAO_BankTransaction
 *
 * @group headless
 */
class CRM_Banking_BAO_BankTransactionTest extends CRM_Banking_TestBase {

  /**
   * Suggestions must be iterable from the most to the least probable no
   * matter in which order they were added — the matcher engine relies on
   * this when it auto-executes the first qualifying suggestion.
   */
  public function testSuggestionsAreOrderedByProbability(): void {
    $matcher_id = $this->createMatcher('match', 'matcher_contribution');
    $plugin = $this->getPluginInstance($matcher_id);
    static::assertInstanceOf(CRM_Banking_PluginModel_Matcher::class, $plugin);
    $transaction = $this->getTransactionInstance($this->createTransaction());
    static::assertInstanceOf(CRM_Banking_BAO_BankTransaction::class, $transaction);

    foreach ([0.5, 0.95, 0.7] as $probability) {
      $suggestion = new CRM_Banking_Matcher_Suggestion($plugin, $transaction);
      $suggestion->setProbability($probability);
      $suggestion->setId("test-{$probability}");
      $transaction->addSuggestion($suggestion);
    }

    $seen = [];
    foreach ($transaction->getSuggestions() as $suggestions) {
      foreach ($suggestions as $suggestion) {
        $seen[] = $suggestion->getProbability();
      }
    }
    static::assertSame([0.95, 0.7, 0.5], $seen, 'Suggestions are not iterated from the most probable one.');
  }

}
