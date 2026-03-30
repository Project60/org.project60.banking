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

namespace Civi\Banking\Matcher\CustomAction;

class CustomActionContext {

  public function __construct(
    private readonly \CRM_Banking_PluginModel_Matcher $matcher,
    private readonly \CRM_Banking_BAO_BankTransaction $btx,
    private readonly \CRM_Banking_Matcher_Suggestion $suggestion,
  ) {}

  public function getValue(string $key): mixed {
    return $this->matcher->getPropagationValue($this->btx, $this->suggestion, $key);
  }

  public function setValue(string $key, mixed $value): void {
    [$keyPrefix, $key] = explode('.', $key, 2) + [NULL, NULL];
    if (NULL === $key) {
      $key = $keyPrefix;
      $keyPrefix = 'suggestion';
    }

    if ('btx' === $keyPrefix) {
      $data = $this->btx->getDataParsed();
      $data[$key] = $value;
      $this->btx->setDataParsed($data);
    }
    elseif ('ba' === $keyPrefix) {
      $data = $this->btx->getBankAccount()?->getDataParsed();
      if (NULL !== $data) {
        $data[$key] = $value;
        $this->btx->getBankAccount()->setDataParsed($data);
      }
    }
    elseif ('party_ba' === $keyPrefix) {
      $data = $this->btx->getPartyBankAccount()?->getDataParsed();
      if (NULL !== $data) {
        $data[$key] = $value;
        $this->btx->getPartyBankAccount()->setDataParsed($data);
      }
    }
    elseif ('suggestion' === $keyPrefix) {
      $this->suggestion->setParameter($key, $value);
    }
    else {
      $this->suggestion->setParameter("$keyPrefix.$key", $value);
    }
  }

}
