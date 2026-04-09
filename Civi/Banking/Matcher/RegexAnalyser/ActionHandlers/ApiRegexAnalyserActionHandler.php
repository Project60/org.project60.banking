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

namespace Civi\Banking\Matcher\RegexAnalyser\ActionHandlers;

use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserActionHandlerInterface;
use Civi\Banking\Matcher\RegexAnalyser\RegexAnalyserMatchContext;

/**
 * Look up parameters via API call.
 *
 *   the 'action' format is: '<entity>:<action>:<result_field>[:multiple]'
 *      EntityName    the CiviCRM API entity
 *      action        the CiviCRM API action
 *      result_field  the field to take from the result
 *      multiple      if this is given, multiple results will be added to the field, separated by comma
 *                      otherwise the result will only be copied if exactly one match was found
 *
 *  further attributes can be given as follows:
 *   const_<param>    set the API parameter to a constant, e.g. const_contact_type = 'Individual'
 *   param_<param>    set the API parameter to the value of another field, e.g. const_first_name = 'first_name'
 */
final class ApiRegexAnalyserActionHandler implements RegexAnalyserActionHandlerInterface {

  public const NAME = 'api';

  private \CRM_Banking_Helpers_Logger $logger;

  public function __construct(
    ?\CRM_Banking_Helpers_Logger $logger = NULL
  ) {
    $this->logger = $logger ?? \CRM_Banking_Helpers_Logger::getLogger();
  }

  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  public function execute(\stdClass $action, RegexAnalyserMatchContext $matchContext): void {
    // compile query
    $params = explode(':', substr($action->action, 4));
    $query = ['return' => $params[2]];
    // @phpstan-ignore foreach.nonIterable
    foreach ($action as $key => $value) {
      if (str_starts_with($key, 'const_')) {
        $query[substr($key, 6)] = $value;
      }
      elseif (str_starts_with($key, 'param_')) {
        $query[substr($key, 6)] = $matchContext->getValue($value) ?? '';
      }
      elseif (str_starts_with($key, 'jsonparam_')) {
        $query[substr($key, 10)] = json_decode((string) ($matchContext->getValue($value) ?? ''), TRUE);
      }
      elseif (str_starts_with($key, 'jsonconst_')) {
        $query[substr($key, 10)] = json_decode($value, TRUE);
      }
    }

    // execute query
    try {
      $this->logger->setTimer('regex:api');
      $matchContext->logMessage("Calling API {$params[0]}.{$params[1]}: " . json_encode($query), 'debug');
      $result = $this->executeAPIQuery($params[0], $params[1], $query, $action, $matchContext);
      $matchContext->logMessage('API result: ' . json_encode($result), 'debug');
      $matchContext->logTime("API {$params[0]}.{$params[1]}", 'regex:api');

      if (isset($params[3]) && $params[3] === 'multiple') {
        // multiple values allowed
        $results = [];
        foreach ($result['values'] as $entity) {
          $results[] = (string) $entity[$params[2]];
        }
        $matchContext->setParsedValue($action->to, implode(',', $results));
      }
      else {
        // only valid if it's the only value
        if ($result['count'] == 1) {
          $entity = reset($result['values']);
          $matchContext->setParsedValue($action->to, $entity[$params[2]]);
        }
      }
    }
    catch (\Exception $e) {
      // @ignoreException
      // TODO: this didn't work... how can we do this?
      $matchContext->logMessage("Exception in API {$params[0]}.{$params[1]}: " . $e->getMessage(), 'debug');
    }
  }

  /**
   * execute API Query
   *
   * @param array<string, mixed> $query
   *
   * @return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  private function executeAPIQuery(string $entity, string $command, array $query, \stdClass $action, RegexAnalyserMatchContext $matchContext): array {
    // phpcs:enable
    $command = strtolower($command);
    if (empty($action->sql) || !in_array($command, ['get', 'getsingle'], TRUE)) {
      // execute via API
      // @phpstan-ignore return.type
      return civicrm_api3($entity, $command, $query);
    }
    else {
      // execute via SQL
      // compile select
      if (empty($query['return'])) {
        $select_clause = '*';
      }
      else {
        $select_clause = $query['return'];
      }

      // compile from
      $from = $this->getTableName($entity);

      // compile where
      $where_clauses = [];
      $query_params = [];
      foreach ($query as $key => $value) {
        if (!in_array($key, ['return', 'sort', 'limit', 'option'], TRUE)) {
          // TODO: support for sort, limit, etc.
          // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf
          if (is_array($value)) {
            $matchContext->logMessage('Support for arrays not implemented, will be ignored', 'warning');
          }
          else {
            $index = count($query_params) + 1;
            $where_clauses[] = "`$key` = %$index";
            $query_params[$index] = [$value, 'String'];
          }
        }
      }
      if ([] === $where_clauses) {
        $where_clause = 'TRUE';
      }
      else {
        $where_clause = '(' . implode(') AND (', $where_clauses) . ')';
      }

      // should there be a limit
      if ($command === 'getsingle') {
        $limit = 'LIMIT 1';
      }
      elseif (!empty($query['limit'])) {
        $limit = "LIMIT {$query['limit']}";
      }
      else {
        $limit = '';
      }

      // execute the query
      /** @var \CRM_Core_DAO $dao_query */
      $dao_query = \CRM_Core_DAO::executeQuery("SELECT {$select_clause} FROM {$from} WHERE {$where_clause} {$limit};", $query_params);
      if ($command === 'getsingle') {
        if ($dao_query->fetch()) {
          return $dao_query->toArray();
        }
        else {
          return civicrm_api3_create_error('Not found');
        }
      }
      // phpcs:disable Squiz.PHP.CommentedOutCode.Found
      // $command == 'get'
      // phpcs:enable
      else {
        $results = [];
        while ($dao_query->fetch()) {
          $results[] = $dao_query->toArray();
        }
        return civicrm_api3_create_success($results);
      }
    }
  }

  /**
   * get the CiviCRM table name for an entity
   */
  private function getTableName(string $entity): string {
    // from: https://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case#1993772
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $entity, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
      $match = $match === strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return 'civicrm_' . implode('_', $ret);
  }

}
