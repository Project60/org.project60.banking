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

namespace Civi\Banking\Matcher\Helper;

final class Api4ResultMapOptions {

  /**
   * @phpstan-param object{
   *   use_all_results?: bool,
   *   index_by?: string,
   *   skip_empty_result?: bool,
   * }&\stdClass $action
   */
  public static function fromObject(\stdClass $action): self {
    return new self(
      $action->use_all_results ?? FALSE,
      $action->index_by ?? NULL,
      $action->skip_empty_result ?? TRUE,
    );
  }

  public function __construct(
    public bool $useAllResults = FALSE,
    public ?string $indexBy = NULL,
    public bool $skipEmptyResult = TRUE,
  ) {}

}
