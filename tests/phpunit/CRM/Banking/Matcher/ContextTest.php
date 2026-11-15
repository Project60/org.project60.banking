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
 * @covers CRM_Banking_Matcher_Context
 *
 * @group headless
 */
final class CRM_Banking_Matcher_ContextTest extends CRM_Banking_TestBase {

  /**
   * A name without matching contacts is looked up once per context.
   */
  public function testNameWithoutMatchesIsLookedUpOnce(): void {
    $btx = $this->getTransactionInstance($this->createTransaction());
    static::assertNotNull($btx);
    $context = new CRM_Banking_Matcher_Context($btx);
    $name = 'Unknown ' . $this->getRandomString(16);

    // Count the name lookups of the context.
    $lookups = 0;
    $listener = function (\Civi\API\Event\PrepareEvent $event) use (&$lookups): void {
      $request = $event->getApiRequest();
      if ('BankingLookup' === $request['entity'] && 'contactbyname' === $request['action']) {
        $lookups++;
      }
    };
    \Civi::dispatcher()->addListener('civi.api.prepare', $listener);
    try {
      $first = $context->lookupContactByName($name);
      $second = $context->lookupContactByName($name);
    }
    finally {
      \Civi::dispatcher()->removeListener('civi.api.prepare', $listener);
    }

    static::assertSame([], $first);
    static::assertSame([], $second);
    static::assertSame(1, $lookups, 'A name without matches should be looked up once.');
  }

}
