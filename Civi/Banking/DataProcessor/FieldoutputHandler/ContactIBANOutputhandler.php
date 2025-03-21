<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Civi\Banking\DataProcessor\FieldoutputHandler;

use Civi\API\Exception\UnauthorizedException;
use Civi\DataProcessor\FieldOutputHandler\AbstractSimpleFieldOutputHandler;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use CRM_Banking_ExtensionUtil as E;

class ContactIBANOutputhandler extends AbstractSimpleFieldOutputHandler {

  /**
   * Returns the label of the field for selecting a field.
   *
   * This could be override in a child class.
   *
   * @return string
   */
  protected function getFieldTitle() {
    return E::ts('Contact ID Field');
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return 'String';
  }

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    static $iban_ref_type = null;
    $iban = '';
    if (is_null($iban_ref_type)) {
      try {
        $iban_ref_type = \Civi\Api4\OptionValue::get(TRUE)
          ->addWhere('option_group_id:name', '=', 'civicrm_banking.reference_types')
          ->addWhere('name', '=', 'IBAN')
          ->execute()
          ->first();
      } catch (UnauthorizedException|\CRM_Core_Exception $e) {

      }
    }
    if ($iban_ref_type) {
      $contactId = $rawRecord[$this->inputFieldSpec->alias] ?? '';
      if ($contactId) {
        $sql = "SELECT `civicrm_bank_account_reference`.`reference`, civicrm_bank_account.* FROM `civicrm_bank_account_reference` INNER JOIN `civicrm_bank_account` ON `civicrm_bank_account_reference`.`ba_id` = `civicrm_bank_account`.`id` WHERE `contact_id` = %1 AND `reference_type_id` = %2 ORDER BY `civicrm_bank_account`.`created_date` DESC LIMIT 0,1";
        $sqlParams = [
          1 => [$contactId, 'Integer'],
          2 => [$iban_ref_type['id'], 'Integer'],
        ];
        try {
          $iban = \CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
        } catch (\CRM_Core_Exception $e) {
          // Do nothing.
        }
      }
    }
    return new FieldOutput($iban);
  }

}