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

use CRM_Banking_ExtensionUtil as E;

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
  public static function get($rule_id) {
    $rule_id = (int) $rule_id;
    if ($rule_id < 1) {
      throw \InvalidArgumentException("Rule ID must be a positive integer");
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_bank_rules WHERE ID = %1", [ 1 => [ $rule_id, 'Integer' ] ]);
    if ($dao->fetch()) {

      // Nb. we cannot use $rule_data = $dao->toArray()
      // because this imposes a string type on the values.
      $obj = new CRM_Banking_Rules_Rule();
      $obj->setFromDao($dao);

      $dao->free();
      return $obj;
    }
    throw new \InvalidArgumentException("Rule not found.");
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

    // truncate key fields to DB lengths
    self::truncateKeyData($params);

    // finally, create
    $obj = new static();
    $obj->setFromArray($params, FALSE);
    $obj->save();
    return $obj;
  }

  /**
   * Take search parameters and return an array of rule objects.
   */
  public static function search($params) {
    $sql = [];
    $sql_params = [];
    $c = 1;

    // Amount min, max describe a range. We want to return any rules whose
    // amount range overlaps this range at all.
    if (!empty($params['amount_min'])) {
      // As we have a minimum, we can say that the amount_max must be greater or equal this.
      $sql_params[$c] = [$params['amount_min'], 'Money'];
      $sql[] = "amount_max >= %$c";
      $c++;
    }
    if (!empty($params['amount_max'])) {
      // As we have a maximum, we can say that the amount_min must be less or equal this.
      $sql_params[$c] = [$params['amount_max'], 'Money'];
      $sql[] = "amount_min <= %$c";
      $c++;
    }

    // Integers
    if (!empty($params['created_by'])) {
      $sql_params[$c] = [$params['created_by'], 'String'];
      $sql[] = "created_by = %" . ($c++);
    }
    foreach ([ 'name', 'party_ba_ref', 'ba_ref', 'party_name', 'tx_reference', 'tx_purpose' ] as $_) {

      if (isset($params[$_])) {
        if ($params[$_]) {
          // we have a value.
          $sql_params[$c] = [$params[$_], 'String', CRM_Core_DAO::QUERY_FORMAT_WILDCARD];
          $sql[] = "$_ LIKE %" . ($c++);
        }
        else {
          // No value, but we have the key, so we test for NULL.
          $sql[] = "$_ IS NULL";
        }
      }
    }
    if (isset($params['is_enabled'])) {
      $sql[] = "is_enabled = " . ( $params['is_enabled'] ? '1' : '0');
    }

    if (!empty($params['last_match_min'])) {
      $sql_params[$c] = [date('YmdHis', strtotime($params['last_match_min'])), 'Timestamp'];
      $sql[] = 'last_match >= %' . ($c++);
    }
    if (!empty($params['last_match_max'])) {
      $sql_params[$c] = [date('YmdHis', strtotime($params['last_match_max'])), 'Timestamp'];
      $sql[] = 'last_match <= %' . ($c++);
    }
    if (!empty($params['match_counter_min'])) {
      $sql_params[$c] = [$params['match_counter_min'], 'Positive'];
      $sql[] = 'match_counter >= %' . ($c++);
    }
    if (!empty($params['match_counter_max'])) {
      $sql_params[$c] = [$params['match_counter_max'], 'Positive'];
      $sql[] = 'match_counter <= %' . ($c++);
    }

    if (!empty($params['conds_like'])) {
      $sql_params[$c] = ["%" . trim($params['conds_like'], '%'). "%", 'String', CRM_Core_DAO::QUERY_FORMAT_WILDCARD];
      $sql[] = "conditions LIKE %" . ($c++);
    }

    $where = $sql ? 'WHERE ' . implode(' AND ', $sql) : '';

    // parse sort.
    $sort = '';
    if (!empty($params['options']['sort'])
      && preg_match('/^(last_match|match_counter) (DE|A)SC$/', $params['options']['sort']  )) {

      $sort = 'ORDER BY ' . $params['options']['sort'];
    }

    // Get results from SQL.
    $sql = "SELECT * FROM civicrm_bank_rules $where $sort";
    $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);


    //
    // Now loop and test conditions and execution criteria.
    //
    if (empty($params['conditions'])) {
      $conditions = [];
    }
    else {
      // Minimal parsing of conditions.
      if (!is_array($params['conditions'])) {
        throw new API_Exception("Expect conditions parameter to be an object.");
      }
      $conditions = $params['conditions'];
    }
    if (empty($params['execution'])) {
      $executions = [];
    }
    else {
      // Minimal parsing of execution.
      if (!is_array($params['execution'])) {
        throw new API_Exception("Expect execution parameter to be an object.");
      }
      $executions = $params['execution'];
    }

    // Unpack data and run other tests on conditions, execution.
    $found = 0;
    // Default to returning 10 rules at a time.
    $offset = (empty($params['options']['offset']) ? 0 : (int)$params['options']['offset']);
    $limit  = (!isset($params['options']['limit']) ? 10 : (int)$params['options']['limit']);
    $results = [];

    // Prepare expressions for condition and execution matches to work as LIKE does.
    $make_regex = function($value) {
      return '/^' . strtr(
        preg_quote($value, '/'), [
          '%' => '.*',
          '_' => '.',
        ]) . '$/i';
    };
    $condition_matches  = array_map($make_regex, $conditions);
    $execution_matches = array_map($make_regex, $executions);

    while ($dao->fetch()) {

      // Load the rule object.
      $obj = new CRM_Banking_Rules_Rule();
      $obj->setFromDao($dao);

      $rule_conditions = $obj->getConditions();
      foreach ($condition_matches as $field => $regex) {
        if (!isset($rule_conditions[$field]['full_match']) || preg_match($regex, $rule_conditions[$field]['full_match']) == 0) {
          // Don't match any further criteria and skip this rule.
          continue 2;
        }
      }
      $rule_executions = $obj->getExecution();
      foreach ($execution_matches as $field => $regex) {

        // Is there a match for this execution in any of the executions?
        $execution_match = FALSE;
        foreach ($rule_executions as $rule_execution) {
          if ($rule_execution['set_param_name'] == $field && preg_match($regex, $rule_execution['set_param_value'])) {
            $execution_match = TRUE;
            break;
          }
        }
        if (!$execution_match) {
          // Don't match any further criteria and skip this rule.
          continue 2;
        }
      }

      $found++;
      if ($found > $offset && (!$limit || ($found <= $offset+$limit))) {
        $results[] = $obj;
      }
      unset($obj);
    }
    $dao->free();

    $results = [
      //'sql'         => $where,
      'total_count' => $found,
      'offset'      => $offset,
      'limit'       => $limit,
      'rules'       => $results,
    ];
    return $results;
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

          if ($this->$prop === NULL) {
            $to_set[] = "`$prop` = NULL";
          }
          else {
            $to_set[] = "`$prop` = %$param_id";
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
        }
        // Execute SQL.
        $sql = 'UPDATE civicrm_bank_rules SET ' . implode(', ', $to_set) . " WHERE id = %$param_id";
        $params[$param_id] = [$this->id, 'Integer'];
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

    $sql = 'DELETE FROM civicrm_bank_rules WHERE id = %1';
    $params = [ 1 => [ $this->id, 'Integer'] ];
    $this->executeQuery($sql, $params);

    // Unset our ID.
    $this->id = NULL;

    return $this;
  }
  /**
   * Set all params from the data array.
   *
   * @param array $data.
   * @param bool $form_database Set TRUE if the data being passed in is direct
   * from the database. This will unserialize() the conditions and execution
   * fields.
   * @return CRM_Banking_Rules_Rule $this.
   */
  public function setFromArray($data, $from_database=TRUE) {
    foreach ($data as $prop=>$value) {
      if ($from_database && ($prop == 'execution' || $prop == 'conditions')) {
        $value = empty($value) ? NULL : unserialize($value);
      }
      $this->genericSetter($prop, $value);
      if ($from_database) {
        // If the data's coming from the database then it's saved.
        unset($this->dirty_props[$prop]);
      }
    }

    return $this;
  }
  /**
   * Set all params from the DAO Object.
   *
   * @param CRM_Core_DAO $dao.
   * @return CRM_Banking_Rules_Rule $this.
   */
  public function setFromDao($dao) {
    $this->id = (int) $dao->id;
    foreach (array_keys($this->props) as $prop) {
      $value = $dao->$prop;
      if ($prop == 'execution' || $prop == 'conditions') {
        $value = empty($value) ? NULL : unserialize($value);
      }

      $this->genericSetter($prop, $value);
    }
    // We're clean because we just loaded from database.
    $this->dirty_props = [];

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
   * Get the rule's name, with a fallback if it's not set
   *
   * @fixme: couldn't overwrite getName method because of Rich's generic implementation
   */
  public function get_Name() {
    $name = $this->getName();
    if (empty($name)) {
      return E::ts("Unnamed Rule [%1]", array(1 => $this->getID()));
    } else {
      return $name;
    }
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

    // Create an array of info with 'primary' info being the first (possibly
    // only) item, and secondary following.
    $execution_info = [];

    if (!empty($this->name)) {
      // Names are optional.
      $execution_info[] = htmlspecialchars($this->name);
    }

    // Create summary of fields the rule matches on.
    $criteria = [];
    foreach ([
        'party_ba_ref',
        'ba_ref',
        'party_name',
        'tx_reference',
        'tx_purpose',
      ] as $field) {

      if ($this->$field !== NULL) {
        $criteria[] = $field;
      }
    }
    if (is_array($this->conditions)) {
      $criteria = array_merge($criteria, array_keys($this->conditions));
    }
    // Create summary of the fields set by this rule.
    $fields_provided = [];
    foreach ($this->execution as $e) {
      if (isset($e['set_param_name'])) {
        $fields_provided[] = $e['set_param_name'];
      }
    }
    $execution_info[] = "Matches on: " . implode(', ', $criteria)
      . " | provides: " . implode(', ', $fields_provided) . '.';

    $variables['execution'] = $execution_info;
  }

  /**
   * Record that this rule was matched.
   *
   * @return CRM_Banking_Rules_Rule $this
   */
  public function recordMatch() {
    $this->setLast_match('now');
    $this->setMatch_counter($this->match_counter + 1);
    $this->save();
    return $this;
  }
  /**
   * Used by the editor.
   */
  public function getRuleData() {
    $data = ['id' => $this->id];
    foreach (array_keys($this->props) as $prop) {
      $data[$prop] = $this->$prop;
    }
    return $data;
  }
  /**
   * Handles validation and casting when setting properties.
   *
   * @param string $prop
   * @param mixed $value
   */
  protected function genericSetter($prop, $value) {

    $this->dirty_props[$prop] = TRUE;

    if ($value === NULL) {
      // NULLs are easy, do them first.
      $this->$prop = NULL;
      return;
    }

    // Deal with casting types.
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
      $this->$prop = date('Y-m-d H:i:s', strtotime($value));
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

  /**
   * Truncate those fields that are restricted in the DB:
   * @param $params array  parameters, will be truncated inline
   * @param $prefix string set, if there is a prefix to the
   */
  public static function truncateKeyData(&$params, $prefix = '') {
    $length_restrictions = array(
        'party_ba_ref' => 64,
        'ba_ref'       => 64,
        'party_name'   => 128,
        'tx_reference' => 128,
        'tx_purpose'   => 255);
    foreach ($length_restrictions as $raw_field_name => $max_length) {
      $field_name = $prefix . $raw_field_name;
      if (isset($params[$field_name]) && (strlen($params[$field_name]) > $max_length)) {
        $params[$field_name] = substr($params[$field_name], 0, $max_length);
        CRM_Core_Error::debug_log_message("Field '{$field_name}' was too long and had to be truncated.");
      }
    }
  }
}
