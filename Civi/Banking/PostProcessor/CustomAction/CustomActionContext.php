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

namespace Civi\Banking\PostProcessor\CustomAction;

class CustomActionContext {

  public function __construct(
    private readonly \CRM_Banking_PluginModel_PostProcessor $postProcessor,
    private readonly \CRM_Banking_Matcher_Suggestion $match,
    private readonly \CRM_Banking_Matcher_Context $matcherContext,
  ) {}

  public function getValue(string $key): mixed {
    if (str_starts_with($key, 'tmp.')) {
      return $this->matcherContext->getCachedEntry($key);
    }

    return $this->postProcessor->getPropagationValue($this->matcherContext->btx, $this->match, $key);
  }

  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public function setValue(string $key, mixed $value): void {
    [$keyPrefix, $key] = explode('.', $key, 2) + [NULL, NULL];
    if (NULL === $key) {
      $key = $keyPrefix;
      $keyPrefix = 'btx';
    }

    $btx = $this->matcherContext->btx;
    if ('btx' === $keyPrefix) {
      $data = $btx->getDataParsed();
      $data[$key] = $value;
      $btx->setDataParsed($data);
    }
    elseif ('tmp' === $keyPrefix) {
      $this->matcherContext->setCachedEntry("tmp.$key", $value);
    }
    elseif ('ba' === $keyPrefix) {
      $data = $btx->getBankAccount()?->getDataParsed();
      if (NULL !== $data) {
        $data[$key] = $value;
        $btx->getBankAccount()->setDataParsed($data);
      }
    }
    elseif ('party_ba' === $keyPrefix) {
      $data = $btx->getPartyBankAccount()?->getDataParsed();
      if (NULL !== $data) {
        $data[$key] = $value;
        $btx->getPartyBankAccount()->setDataParsed($data);
      }
    }
    elseif ('match' === $keyPrefix) {
      $this->match->setParameter($key, $value);
    }
    else {
      throw new \InvalidArgumentException("Unknown key prefix '$keyPrefix'");
    }
  }

  public function log(string $message): void {
    $this->postProcessor->logMessage($message);
  }

}
