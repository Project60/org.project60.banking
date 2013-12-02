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

    if (!$name) {
      // no name given, no results:
      return $contacts_found;
    }

  	// create some mutations, since quick search is a bit picky
  	$name_bits = preg_split("( |,|&)", $name, 0, PREG_SPLIT_NO_EMPTY);
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
	  	$query['name'] = implode(', ', $name_bits);
	  	$result = civicrm_api('Contact', 'getquick', $query);
	  	if ($result['is_error']) {
	  		// that didn't go well...
	  		CRM_Core_Session::setStatus(ts("Internal error while looking up contacts."), ts('Error'), 'alert');
	  	} else {
	  		foreach ($result['values'] as $contact) {
          // get the current maximum similarity...
          if (isset($contacts_found[$contact['id']])) {
            $probability = $contacts_found[$contact['id']]; 
          } else {
            $probability = 0.0;
          }

          // now, we'll have to find the maximum similarity with any of the name mutations
          foreach ($name_mutations as $compare_name_bits) {
            $compare_name = implode(', ', $compare_name_bits);
            //$new_probability = (similar_text(strtolower($compare_name), strtolower($contact['sort_name']))) / ((strlen($name)+strlen($contact['sort_name']))/2.0);
            $new_probability = 0.0;
            similar_text(strtolower($compare_name), strtolower($contact['sort_name']), $new_probability);
            $new_probability /= 100.0;
            if ($new_probability > $probability) {
              $probability = $new_probability;
            }
          }

          $contacts_found[$contact['id']] = $probability;
	  		}
	  	}
  	}

    // norm the array, i.e. if there is multiple IDs that have probability 1.0, decrease their probability...
    $total = array_sum($contacts_found);
    if ($total >= 1.0) {
      $total += 0.01;
      $factor = 1.0 / $total;
      $factor *= $factor;
      foreach ($contacts_found as $contact_id => $probability) {
        $contacts_found[$contact_id] = $probability * $factor;
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