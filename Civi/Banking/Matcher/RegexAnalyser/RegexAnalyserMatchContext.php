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

namespace Civi\Banking\Matcher\RegexAnalyser;

class RegexAnalyserMatchContext {

  /**
   * @param array<string, list<string>> $matchData
   *   Matches of preg_match_all().
   */
  public function __construct(
    private readonly array $matchData,
    private readonly int $matchIndex,
    public readonly \stdClass $rule,
    private readonly \CRM_Banking_PluginImpl_Matcher_RegexAnalyser $analyser,
    private readonly \CRM_Banking_BAO_BankTransaction $btx,
    private readonly \CRM_Banking_Helpers_Logger $logger,
  ) {}

  /**
   * Get the value either from the match context, or the already stored data
   */
  public function getValue(string $key): mixed {
    $matchedValue = $this->getMatchedValue($key);
    $dataParsed = $this->btx->getDataParsed();
    // see https://github.com/Project60/org.project60.banking/issues/111
    if ($this->analyser->getConfig()->variable_lookup_compatibility) {
      // @phpstan-ignore empty.notAllowed
      if (!empty($matchedValue)) {
        return $matchedValue;
      }
      // @phpstan-ignore empty.notAllowed
      elseif (!empty($dataParsed[$key])) {
        return $dataParsed[$key];
      }
      else {
        // try value propagation
        $value = $this->analyser->getPropagationValue($this->btx, NULL, $key);
        if ($value) {
          return $value;
        }
        else {
          $this->analyser->logMessage("RegexAnalyser - Cannot find source '$key' for rule or filter.", 'debug');

          return NULL;
        }
      }
    }
    else {
      if (NULL !== $matchedValue) {
        return $matchedValue;
      }
      elseif (isset($dataParsed[$key])) {
        return $dataParsed[$key];
      }
      else {
        // try value propagation
        $value = $this->analyser->getPropagationValue($this->btx, NULL, $key);
        if (NULL === $value) {
          $this->analyser->logMessage("RegexAnalyser - Cannot find source '$key' for rule or filter.", 'debug');
        }

        return $value;
      }
    }
  }

  public function getMatchedValue(string $key): ?string {
    return $this->matchData[$key][$this->matchIndex] ?? NULL;
  }

  /**
   * @return array<string, string>
   */
  public function getMatchedValues(): array {
    return array_map(fn (array $values) => $values[$this->matchIndex], $this->matchData);
  }

  public function getParsedValue(string $key): mixed {
    return $this->btx->getDataParsed()[$key] ?? NULL;
  }

  public function removeParsedValue(string $key): void {
    $dataParsed = $this->btx->getDataParsed();
    unset($dataParsed[$key]);
    $this->btx->setDataParsed($dataParsed);
  }

  public function setParsedValue(string $key, mixed $value): void {
    $this->btx->setDataParsed([$key => $value] + $this->btx->getDataParsed());
  }

  public function setValue(string $key, mixed $value): void {
    [$keyPrefix, $key] = explode('.', $key, 2) + ['', NULL];
    if (NULL === $key) {
      $key = $keyPrefix;
      $keyPrefix = 'btx';
    }

    if ('ba' === $keyPrefix) {
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
    else {
      $this->setParsedValue($key, $value);
    }
  }

  /**
   * set a timer for later use with logTime
   */
  public function setLogTimer(string $timer): void {
    $this->logger->setTimer($timer);
  }

  public function logMessage(string $message, string $logLevel = 'debug'): void {
    $this->analyser->logMessage($message, $logLevel);
  }

  public function logTime(string $process, string $timer): void {
    $this->analyser->logTime($process, $timer);
  }

}
