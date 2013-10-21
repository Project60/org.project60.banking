<?php

class CRM_Banking_Matcher_Context {
  
  public $btx;

  // will store generic attributes from the various matchers
  private $_attributes = array();

  // will store cached data needed/produced by the helper functions
  private $_caches;
  
  public function __construct( CRM_Banking_BAO_BankTransaction $btx ) {
    $this->btx = $btx;
  }


  /**
   * Will provide a name based lookup for contacts
   *
   * @return array(contact_id => similarity), where similarity is from [0..1]
   */
  public function lookupContactByName($name, $parameters=array()) {

  	// TODO: check for cached data

  	$contacts_found = array();

  	// create some mutations, since quick search is a bit picky
  	$name_bits = explode(' ', $name);
  	$name_mutations = array();
  	$name_mutations[] = $name_bits;
  	if (count($name_bits)>1) {
  		$name_mutations[] = array_reverse($name_bits);
  	}
  	if (count($name_bits)>2) {
  		$reduced_name = array($name_bits[0], $name_bits[count($name_bits)-1]);
  		$name_mutations[] = $reduced_name;
  		$name_mutations[] = array_reverse($reduced_name);
  	}

	// query quicksearch for each combination
  	foreach ($name_mutations as $name_bits) {
	  	$query = array('version' => 3);
	  	$query['display_name'] = implode(' ', $name_bits);
	  	$result = civicrm_api('Contact', 'getquick', $query);
	  	if (isset($result['is_error']) && $result['is_error']) {
	  		// that didn't go well...
	  		CRM_Core_Session::setStatus(ts("Internal error while looking up contacts."), ts('Error'), 'alert');
	  	} else {
	  		foreach ($result['values'] as $contact) {
	  			$probability = (similar_text($name, $contact['display_name'])) / ((strlen($name)+strlen($contact['display_name']))/2.0);
	  			if (!isset($contacts_found[$contact['id']]) || $contacts_found[$contact['id']] < $probability) {
	  				$contacts_found[$contact['id']] = $probability;
	  			}
	  		}
	  	}
  	}

  	// TODO: cache results
  	return $contacts_found;
  }


  /**
   * Will rate a contribution on whether it would match the bank payment
   *
   * @return array(contribution_id => score), where score is from [0..1]
   */
  public function rateContribution($contribution, $parameters=array()) {
  	// TODO: check for cached data

  	$amount_diff = abs($contribution['total_amount'] - $this->btx->amount);
  	$amount_avg = ($contribution['total_amount'] + $this->btx->amount) / 2.0;
  	$amount_score = 1.0 - $amount_diff / $amount_avg;

  	// TODO: rate dates
  	$date_score = 1.0;

  	// TODO: currencies?

  	// TODO: cache results
  	return $amount_score * $date_score;
  }
}