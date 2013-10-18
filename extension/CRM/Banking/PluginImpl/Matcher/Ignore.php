<?php

/**
 * The generic match plugin is able to do the following :
 * - check the transaction amount to be inside a range
 * - check the transaction date to be inside a range
 * - check the communication using a regex
 */
class CRM_Banking_PluginImpl_Matcher_Ignore extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    // this section will be refactored to use different conditions, but for now, this is hardcoded
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);

    $config = $this->_plugin_config;

    if (isset($config->ignore)) {
      // iterate through the ignore list
      foreach ($config->ignore as $ignore_record) {
        if ($this->matches_pattern($ignore_record, $btx, $context)) {
          if (isset($ignore_record->precision)) {
            $suggestion->addEvidence($ignore_record->precision, $ignore_record->message);
          } else {
            $suggestion->addEvidence(1, $ignore_record->message);
          }
        }
      }
    }

    if ($suggestion->getProbability() > 0) {
      $btx->addSuggestion($suggestion);
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }


  /**
   * Handle the different actions, should probably be handles at base class level ...
   * 
   * @param type $match
   * @param type $btx
   */
  public function execute($suggestion, $btx) {
    // this is the IGNORE action. Simply set the status to ignored
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Ignored');
    $btx->setStatus($newStatus);
  }


  /**
   * check if this ignore pattern applies to this btx
   */
  private function matches_pattern($ignore_record, CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    // collect all the fields
    $fields = array();
    if (isset($ignore_record->field)) {
      array_push($fields, $ignore_record->field);
    }

    if (isset($ignore_record->fields)) {
      $fields = array_merge($fields, $ignore_record->fields);
    }

    // extract the values
    $values = array();
    foreach ($fields as $field) {
      if (isset($btx->$field)) {
        array_push($values, $btx->$field);
      } else if (isset($btx->getDataParsed()[$field])) {
        array_push($values, $btx->getDataParsed()[$field]);
      }
    }

    if (isset($ignore_record->regex)) {
      foreach ($values as $value) {
        if (preg_match($ignore_record->regex, $value)) {
          return true;
        }
      }
    }
    
    return false;
  }
  

}

