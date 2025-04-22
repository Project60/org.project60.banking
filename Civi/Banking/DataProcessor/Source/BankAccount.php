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

namespace Civi\Banking\DataProcessor\Source;

use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SqlJoinInterface;
use Civi\DataProcessor\DataFlow\SqlDataFlow\MultiValueFieldWhereClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\DataSpecification\Utils as DataSpecificationUtils;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

class BankAccount extends AbstractCivicrmEntitySource {

  /**
   * @var null|SqlTableDataFlow
   */
  protected $baReferenceFlow = null;

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity()
  {
    return 'BankAccount';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable()
  {
    return 'civicrm_bank_account';
  }

  /**
   * Ensure that the entity table is added the to the data flow.
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Exception
   */
  protected function ensureEntity()
  {
    if (empty($this->baReferenceFlow)) {
      $aliasPrefix = '_ba_reference';
      if ($this->getSourceName()) {
        $aliasPrefix = $this->getSourceName() . $aliasPrefix;
      }
      $this->baReferenceFlow = new SqlTableDataFlow('civicrm_bank_account_reference', $aliasPrefix);
      $join = new SimpleJoin($this->getSourceName(), 'id', $aliasPrefix, 'ba_id', 'LEFT');
      $join->setDataProcessor($this->dataProcessor);
      $additionalDataFlowDescription = new DataFlowDescription($this->baReferenceFlow, $join);
      $this->additionalDataFlowDescriptions[$aliasPrefix] = $additionalDataFlowDescription;
    }
    return parent::ensureEntity();
  }

  protected function reset()
  {
    parent::reset();
    $this->baReferenceFlow = null;
  }


  /**
   * Load the fields from this entity.
   *
   * @param DataSpecification $dataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  protected function loadFields(DataSpecification $dataSpecification, $fieldsToSkip=array()) {
    parent::loadFields($dataSpecification, $fieldsToSkip);
    $daoClass = \CRM_Dataprocessor_Utils_Tables::getDAONameForEntity('BankAccountReference');
    $aliasPrefix = '_ba_reference';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . $aliasPrefix;
    }
    DataSpecificationUtils::addDAOFieldsToDataSpecification($daoClass, $dataSpecification, ['id', 'ba_id'], '', $aliasPrefix);
  }

  /**
   * Ensure that filter or aggregate field is accesible in the query
   *
   * @param FieldSpecification $field
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   * @throws \Exception
   */
  public function ensureField(FieldSpecification $field) {
    $aliasPrefix = '_ba_reference';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . $aliasPrefix;
    }
    if ($this->getAvailableFilterFields()->doesAliasExists($field->alias)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByAlias($field->alias);
    } elseif ($this->getAvailableFilterFields()->doesFieldExist($field->name)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByName($field->name);
    }
    if ($spec && stripos($spec->alias, $aliasPrefix) === 0) {
      $this->ensureEntity();
      return $this->baReferenceFlow;
    } else {
      $return = parent::ensureField($field);
    }
    return $return;
  }

  /**
   * Ensures a field is in the data source
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @throws \Exception
   */
  public function ensureFieldInSource(FieldSpecification $fieldSpecification) {
    $originalFieldSpecification = null;
    if ($this->getAvailableFields()->doesAliasExists($fieldSpecification->alias)) {
      $originalFieldSpecification = $this->getAvailableFields()->getFieldSpecificationByAlias($fieldSpecification->alias);
    } elseif ($this->getAvailableFields()->doesFieldExist($fieldSpecification->name)) {
      $originalFieldSpecification = $this->getAvailableFields()
        ->getFieldSpecificationByName($fieldSpecification->name);
    }
    $aliasPrefix = '_ba_reference';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . $aliasPrefix;
    }
    if (stripos($originalFieldSpecification->alias, $aliasPrefix) === 0) {
      if ($originalFieldSpecification && (!$this->isAggregationEnabled() || $this->getAggregateField() != $originalFieldSpecification->name)) {
        $this->ensureEntity();
        $this->baReferenceFlow->getDataSpecification()->addFieldSpecification($fieldSpecification->alias, $fieldSpecification);
      }
    } else {
      parent::ensureFieldInSource($fieldSpecification);
    }
  }

  /**
   * Adds an inidvidual filter to the data source
   *
   * @param $filter_field_alias
   * @param $op
   * @param $values
   *
   * @throws \Exception
   */
  protected function addFilter($filter_field_alias, $op, $values) {
    $spec = null;
    if ($this->getAvailableFields()->doesAliasExists($filter_field_alias)) {
      $spec = $this->getAvailableFields()->getFieldSpecificationByAlias($filter_field_alias);
    } elseif ($this->getAvailableFields()->doesFieldExist($filter_field_alias)) {
      $spec = $this->getAvailableFields()->getFieldSpecificationByName($filter_field_alias);
    }
    $aliasPrefix = '_ba_reference';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . $aliasPrefix;
    }
    if (stripos($spec->alias, $aliasPrefix) === 0) {
      $this->ensureEntity();
      $tableAlias = $this->baReferenceFlow->getTableAlias();
      if ($spec->isMultiValueField()) {
        $clause = new MultiValueFieldWhereClause($tableAlias, $spec->name, $op, $values, $spec->type, TRUE);
      } else {
        $clause = new SimpleWhereClause($tableAlias, $spec->name,$op, $values, $spec->type, TRUE);
      }
      $this->baReferenceFlow->addWhereClause($clause);
      $this->addFilterToAggregationDataFlow($spec, $op, $values);
    } else {
      parent::addFilter($filter_field_alias, $op, $values);
    }
  }


}