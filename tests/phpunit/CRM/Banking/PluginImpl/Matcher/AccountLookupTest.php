<?php
/*
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
 * @covers CRM_Banking_PluginImpl_Matcher_AccountLookup
 *
 * @group headless
 */
final class CRM_Banking_PluginImpl_Matcher_AccountLookupTest extends CRM_Banking_TestBase {

  private const IBAN = 'DE89370400440532013000';

  /**
   * The second lookup of a reference in the same transaction comes from the
   * cache and must still return the account.
   */
  public function testCachedReferenceReturnsAccount(): void {
    $account = $this->callAPISuccess('BankingAccount', 'getorcreate', [
      'contact_id' => $this->createContact(),
      'reference' => self::IBAN,
      'reference_type' => 'IBAN',
    ]);

    // Own and party account carry the same reference, so the party lookup
    // hits the cache entry that the own account lookup just stored.
    $transactionId = $this->createTransaction([
      '_IBAN' => self::IBAN,
      '_party_IBAN' => self::IBAN,
    ]);
    $this->createMatcher('match', 'analyser_account', [
      'lookup_org_account_mode' => 'update',
      'lookup_donor_account_mode' => 'update',
    ]);

    $this->runMatchers();

    $transaction = $this->getTransaction($transactionId);
    static::assertEquals($account['id'], $transaction['ba_id'] ?? NULL, 'The own bank account is not set.');
    static::assertEquals($account['id'], $transaction['party_ba_id'] ?? NULL, 'The party bank account is not set.');
  }

}
