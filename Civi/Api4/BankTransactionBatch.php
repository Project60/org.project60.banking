<?php
declare(strict_types = 1);

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;
use Civi\Banking\Api4\Action\BankTransaction\GetAction;
use Civi\Banking\Api4\Traits\TransactionPermissionsTrait;

final class BankTransactionBatch extends DAOEntity {

  use TransactionPermissionsTrait;

  public static function get($checkPermissions = TRUE) {
    return (new GetAction(self::getEntityName(), __FUNCTION__))->setCheckPermissions($checkPermissions);
  }

}
