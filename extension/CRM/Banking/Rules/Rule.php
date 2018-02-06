<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This class represents a "Matching Rule" as used
 *  by the rule-based matcher: CRM_Banking_PluginImpl_Matcher_Rules
 */
class CRM_Banking_Rules_Rule {

  // Database columns.
  protected $id;
  protected $amount_min;
  protected $amount_max;
  protected $party_ba_ref;
  protected $ba_ref;
  protected $party_name;
  protected $tx_reference;
  protected $tx_purpose;
  /** This is serialized on save() and unserialized on load. */
  protected $conditions;
  /** This is serialized on save() and unserialized on load. */
  protected $execution;
  protected $name;
  /** FIXME documentation needed for type */
  protected $type = 1;
  /** Should the rule be used? */
  protected $is_enabled = 1;
  /** Rule should not be used on or after this date */
  protected $valid_until;
  /** CiviCRM Contact ID */
  protected $created_by;
  protected $match_counter = 0;
  /** Date */
  protected $last_match;

  protected $props = [
    'amount_min'    => TRUE,
    'amount_max'    => TRUE,
    'party_ba_ref'  => TRUE,
    'ba_ref'        => TRUE,
    'party_name'    => TRUE,
    'tx_reference'  => TRUE,
    'tx_purpose'    => TRUE,
    'conditions'    => TRUE,
    'execution'     => TRUE,
    'name'          => TRUE,
    'type'          => TRUE,
    'is_enabled'    => TRUE,
    'valid_until'   => TRUE,
    'created_by'    => TRUE,
    'match_counter' => TRUE,
    'last_match'    => TRUE,
  ];
  protected $dirty_props = [];

  /** For testing: NULL or callback to mock CRM_Core_DAO::executeQuery */
  public $db_execute_method;
  /** For testing: NULL or callback to mock CRM_Core_DAO::singleValueQuery */
  public $db_single_value_query_method;

  /**
   * Load a rule based on ID
   *
   * @throws InvalidArgumentException if rule_id invalid or not found.
   *
   * @param int $rule_id
   * @return CRM_Banking_Rules_Rule object representing the loaded rule.
   */
  public static function getRule($rule_id) {
    $rule_id = (int) $rule_id;
    if ($rule_id < 1) {
      throw \InvalidArgumentException("Rule ID must be a positive integer");
    }

    $dao = $this->executeQuery("SELECT * FROM civicrm_bank_rules WHERE ID = ?", [ 1 => [ $rule_id, 'integer' ] ]);
    if ($dao->fetch()) {
      $rule = $dao->toArray();
      $obj = new static();
      $obj->setFromArray($rule);
      $dao->free();
      return $rule;
    }
    throw \InvalidArgumentException("Rule not found.");
  }

  /**
   * Create rule.
   *
   * @param array $params. Map of column name to value.
   * @return CRM_Banking_Rules_Rule
   */
  public static function createRule($params) {
    if (!isset($params['created_by'])) {
      $params['created_by'] = CRM_Core_Session::singleton()->getLoggedInContactID();
    };
    $obj = new static();
    $obj->setFromArray($params);
    $obj->save();
    return $obj;
  }


  /**
   * Save rule to database.
   *
   * @return CRM_Banking_Rules_Rule $this
   */
  public function save() {
    if ($this->id) {
      // Update.
      if ($this->dirty_props) {
        $to_set = [];
        $param_id = 1;
        foreach (array_keys($this->dirty_props) as $prop) {

          $to_set[] = "`$prop` = ?";
          switch($prop) {
            case 'type':
            case 'is_enabled':
            case 'created_by':
            case 'match_counter':
              $params[$param_id++] = [$this->$prop, 'Integer'];
              break;

            case 'conditions':
            case 'execution':
              $params[$param_id++] = [serialize($this->$prop), 'String'];
              break;

            default:
              $params[$param_id++] = [$this->$prop, 'String'];
              break;
          }
        }
        // Execute SQL.
        $sql = 'UPDATE civicrm_bank_rules SET ' . implode(', ', $to_set) . ' WHERE id = ?';
        $params[$param_id] = $this->id;
        $this->executeQuery($sql, $params);
      }
    }
    else {
      // No ID yet, must be a new record.
      $params = [];
      foreach (array_keys($this->props) as $prop) {
        $params[$prop] = $this->$prop;
      }
      $params['conditions'] = serialize($params['conditions']);
      $params['execution']  = serialize($params['execution']);

      // Create record in database.
      $insert = CRM_Utils_SQL_Insert::into('civicrm_bank_rules')->row($params)->toSQL();
      $this->executeQuery($insert);
      // Store the new ID as our own.
      $this->id = $this->singleValueQuery('SELECT LAST_INSERT_ID()');
    }

    // All clean.
    $this->dirty_props = [];

    return $this;
  }
  /**
   * Delete rule from database.
   *
   * @return CRM_Banking_Rules_Rule $this
   */
  public function delete() {
    if (!$this->id) {
      throw new Exception("Attempt to delete a rule that has no ID.");
    }

    $sql = 'DELETE FROM civicrm_bank_rules WHERE id = ?';
    $params = [ 1 => [ $this->id, 'Integer '] ];
    $this->executeQuery($sql, $params);

    // Unset our ID.
    $this->id = NULL;

    return $this;
  }
  /**
   * Set all params from the data array.
   *
   * @param array $data.
   * @return CRM_Banking_Rules_Rule $this.
   */
  public function setFromArray($data) {
    foreach ($data as $prop=>$value) {
      $this->genericSetter($prop, $value);
    }

    return $this;
  }
  /**
   * get rule's ID
   */
  public function getID() {
    return $this->id;
  }

  /**
   * Provides getter and setter functions for all properties.
   *
   * Creates methods named get<Property> and set<Property>
   * Uppercase the first character of the column name e.g.
   *
   * $obj->setName('fred');
   * $obj->getName(); // 'fred'
   *
   * Note: setter returns the object, so setting can be chained:
   * $obj
   *   ->setName('fred')
   *   ->setEnabled(0);
   *
   * @return getters return the property value; setters return CRM_Banking_Rules_Rule $this.
   */
  public function __call($method, $args) {
    if (preg_match('/^([sg]et)([A-Z].*)$/', $method, $matches)) {
      $prop = strtolower(substr($matches[2], 0, 1)) . substr($matches[2], 1);
      if (!isset($this->props[$prop])) {
        throw new InvalidArgumentException("Trying to $matches[1] on '$prop' which is not a valid property");
      }

      if ($matches[1] == 'get') {
        // Generic getter.
        return $this->$prop;
      }
      // Setter.
      if ($prop == 'id') {
        throw new InvalidArgumentException("Setting ID is not permitted.");
      }
      if (count($args) != 1) {
        throw new InvalidArgumentException("Call set with one parameter.");
      }
      $this->genericSetter($prop, $args[0]);
      return $this;
    }
    throw new Exception("Call to undefined method $method");
  }
  /**
   * Return the type of rule:
   *  'analyser rules' will only update the btx data, while
   *  'matcher rules'  will actually go ahead and create contributions
   */
  public function isAnalyserRule() {
    return TRUE; // for now we'll only implement these
  }

  /**
   * Adds all parameters needed to the given
   * variable set. These will end up in the
   * smarty template
   */
  public function addRenderParameters(&$variables) {
    // TODO
  }

  /**
   * Handles validation and casting when setting properties.
   *
   * @param string $prop
   * @param mixed $value
   */
  protected function genericSetter($prop, $value) {
    if ($value === NULL) {
      // NULLs are easy, do them first.
      $this->$prop = NULL;
      return;
    }
    switch($prop) {
    case 'type':
    case 'created_by':
    case 'match_counter':
      // Positive Integers
      $_ = (int) $value;
      if ($_ < 0) {
        throw new InvalidArgumentException("$prop cannot be negative");
      }
      $this->$prop = $_;
      break;

    case 'is_enabled':
      // Bool.
      $this->$prop = $value ? 1 : 0;
      break;

    case 'valid_until':
    case 'last_match':
      // Dates.
      $this->$prop = date('c', strtotime($value));
      break;

    case 'amount_min':
    case 'amount_max':
      // Floats
      $this->$prop = (double) $value;
      break;

    default:
      // Default (strings)
      $this->$prop = $value;
    }
    $this->dirty_props[$prop] = TRUE;
  }

  /**
   * Allows mocking CRM_Core_DAO::executeQuery.
   *
   * @param string $sql
   * @param array $params.
   */
  protected function executeQuery($sql, $params=[]) {
    if ($this->db_execute_method) {
      $callable = $this->db_execute_method;
      return $callable($sql, $params);
    }
    else {
      return CRM_Core_DAO::executeQuery($sql, $params);
    }
  }
  /**
   * Allows mocking CRM_Core_DAO::singleValueQuery.
   *
   * @param string $sql
   * @param array $params.
   */
  protected function singleValueQuery($sql, $params=[]) {
    if ($this->db_single_value_query_method) {
      $callable = $this->db_single_value_query_method;
      return $callable($sql, $params);
    }
    else {
      return CRM_Core_DAO::singleValueQuery($sql, $params);
    }
  }
}
