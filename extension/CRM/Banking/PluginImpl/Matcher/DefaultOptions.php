<?php

/**
 * The Default Options Matcher will provide the user with two default (last resort) options:
 *  1) Mark the payment as "ignored"
 *  2) Allow the manual assiciation of contributions
 */
class CRM_Banking_PluginImpl_Matcher_DefaultOptions extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->manual_enabled)) $config->manual_enabled = true;
    if (!isset($config->manual_probability)) $config->manual_probability = 0.1;
    if (!isset($config->manual_show_always)) $config->manual_show_always = true;
    if (!isset($config->manual_title)) $config->manual_title = "Manually processed.";
    if (!isset($config->manual_message)) $config->manual_message = "Please configure";
    if (!isset($config->manual_contribution)) $config->manual_contribution = "Contribution:";
    if (!isset($config->default_financial_type_id)) $config->default_financial_type_id = 1;

    if (!isset($config->ignore_enabled)) $config->ignore_enabled = true;
    if (!isset($config->ignore_probability)) $config->ignore_probability = 0.1;
    if (!isset($config->ignore_show_always)) $config->ignore_show_always = true;
    if (!isset($config->ignore_title)) $config->ignore_title = "Not Relevant";
    if (!isset($config->ignore_message)) $config->ignore_message = "Please configure";
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {

    $config = $this->_plugin_config;

    // create 'manually processed' suggestion, if applicable
    if ($config->manual_enabled) {
      if ($config->manual_show_always || $this->has_other_suggestions($btx)) {
        $manually_processed = new CRM_Banking_Matcher_Suggestion($this, $btx);
        $manually_processed->addEvidence($this->get_probability($config->manual_probability, $btx));
        $manually_processed->setTitle($config->manual_title);
        $manually_processed->setId('manual');
        $btx->addSuggestion($manually_processed);
      }
    }

    // create 'not relevant' suggestion, if applicable
    if ($config->ignore_enabled) {
      if ($config->ignore_show_always || $this->has_other_suggestions($btx)) {
        $not_relevant = new CRM_Banking_Matcher_Suggestion($this, $btx);
        $not_relevant->addEvidence($this->get_probability($config->ignore_probability, $btx));
        $not_relevant->setTitle($config->ignore_title);
        $not_relevant->setId('ignore');
        $btx->addSuggestion($not_relevant);
      }
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
    if ($suggestion->getId()==="manual") {
      $cids = $suggestion->getParameter('contribution_ids');
      $contribution_count = 0;
      if ($cids) {

        $completed_status = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');
        foreach ($cids as $cid) {
          if ($cid) {
            $query = array('version' => 3, 'id' => $cid);
            $query['contribution_status_id'] = $completed_status;
            $query['is_test'] = 0;
            $query['receive_date'] = date('YmdHis', strtotime($btx->booking_date));
            $result = civicrm_api('Contribution', 'create', $query);
            if (isset($result['is_error']) && $result['is_error']) {
              CRM_Core_Session::setStatus(ts("Couldn't modify contribution."), ts('Error'), 'error');
            } else {
              $contribution_count += 1;
            }
          }
        }

        if ($contribution_count > 0) {
          $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
          $btx->setStatus($newStatus);
          parent::execute($suggestion, $btx);
        } else {
          CRM_Core_Session::setStatus(ts("The contribution is not valid. The payment is NOT completed."), ts('Payment NOT completed.'), 'alert');
        }

      }  else {
        CRM_Core_Session::setStatus(ts("No contribution given. The payment is NOT completed."), ts('Payment NOT completed.'), 'alert');
      }
    } else {
      // this is the IGNORE action. Simply set the status to ignored
      $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Ignored');
      $btx->setStatus($newStatus);
      parent::execute($suggestion, $btx);
    }
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    if ($match->getId() === "manual") {
      if (isset($parameters["manual_match_contributions"])) {
        $contributions = explode(",", $parameters["manual_match_contributions"]);
        $match->setParameter('contribution_ids', $contributions);
      }
    }
  }

    /** 
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_match( CRM_Banking_Matcher_Suggestion $match, $btx) {
    if ($match->getId() === "manual") {
      $data_parsed = $btx->getDataParsed();
      $booking_date = date('YmdHis', strtotime($btx->booking_date));
      $status_pending = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Pending');
      $new_contribution_link = CRM_Utils_System::url("civicrm/contribute/add", "reset=1&action=add&context=standalone");
      $edit_contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=update&reset=1&id=__contributionid__&cid=__contactid__&context=home");
      $view_contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id=__contributionid__&cid=__contactid__&context=home");

      $snippet  = "<div>" . ts("Please manually process this payment and <b>then</b> add the resulting contributions to this list.");
      $snippet .= "<input type=\"hidden\" id=\"manual_match_contributions\" name=\"manual_match_contributions\" value=\"\"/></div>";    // this will hold the list of contribution ids

      // add the buttons
      $snippet .= "<br/><div>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_refresh_list();\"><span><div class=\"icon refresh-icon\"></div>" . ts("refresh") . "</span></a>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_create_contribution();\"><span><div class=\"icon add-icon\"></div>" . ts("create new") . "</span></a>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_add_contribution();\"><span><div class=\"icon add-icon\"></div>" . ts("add by ID") . ":</span></a>";
      $snippet .= "<input id=\"manual_match_add\" onkeydown=\"if (event.keyCode == 13) return manual_match_add_contribution();\" type=\"text\" style=\"width: 4em; height: 1.4em;\"></input>";
      // FIXME: could somebody please replace this with sth that works?
      $snippet .= "<span align=\"right\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>";
      $snippet .= "<span id=\"manual_match_contribution_sum\" align=\"right\" style=\"color: red; font-weight: bold;\"><b>".ts("sum").": 0.00 EUR</b></span>";
      $snippet .= "</div>";

      // add the table
      $snippet .= "<br/><table>";
      $snippet .= "<th></th>";
      $snippet .= "<th>".ts("Contact")."</th>";
      $snippet .= "<th>".ts("Type")."</th>";
      $snippet .= "<th>".ts("Date")."</th>";
      $snippet .= "<th>".ts("Status")."</th>";
      $snippet .= "<th align=\"right\">".ts("Amount")."</th>";
      $snippet .= "<tbody id=\"manual_match_contribution_table\">";
      $snippet .= "</tbody></table>";
      
      // add the java script
      $snippet .= '
        <script type="text/javascript">
          function manual_match_refresh_list() {
            // clear the table
            cj("#manual_match_contribution_table tr").remove();

            // then rebuild with the cids in the list
            var list = cj("#manual_match_contributions").val().split(",");
            for (cid_idx in list) {
              cid = parseInt(list[cid_idx]);
              if (!isNaN(cid) && cid>0) {
                // load the contribution
                CRM.api("Contribution", "get", {"q": "civicrm/ajax/rest", "sequential": 1, "id": cid},
                  { success: manual_match_add_data_to_list });
              }
            }
          }

          function manual_match_add_data_to_list(data) {
            if (data.count>0) {
              var contribution = data.values[0];
              manual_match_add_contribution_to_field(contribution.id);
              
              // add to table, if not already there
              if (!cj("#manual_match_row_cid_" + contribution.id).length) {
                var view_link = cj("<div/>").html("'.$view_contribution_link.'").text();
                view_link = view_link.replace("__contributionid__", contribution.id);
                view_link = view_link.replace("__contactid__", contribution.contact_id);

                var row = "<tr id=\"manual_match_row_cid_" + contribution.id + "\">";
                row += "<td><a href=\"\" onclick=\"manual_match_remove_contribution(" + contribution.id + ");\">['.ts('remove').']</a>";
                row += "&nbsp;<a href=\"" + view_link + "\" target=\"_blank\">['.ts('view').']</a></td>";
                row += "<td>" + contribution.display_name + "</td>";
                row += "<td>" + contribution.financial_type + "</td>";
                row += "<td>" + contribution.receive_date.replace(" 00:00:00","");  + "</td>";
                row += "<td>" + contribution.contribution_status + "</td>";
                row += "<td name=\"amount\" align=\"right\">" + parseFloat(contribution.total_amount).toFixed(2) + " " + contribution.currency + "</td>";
                row += "</tr>";
                cj("#manual_match_contribution_table").append(row);
                manual_match_update_sum();
              }
            }
          }

          function manual_match_update_sum() {
            // sum up the rows
            var sum = 0.0;
            cj("#manual_match_contribution_table tr").each(function() {
              sum += parseFloat(cj("td[name=amount]", this).text());
            });

            // update the style...
            if (sum == parseFloat('.$btx->amount.')) {
              cj("#manual_match_contribution_sum").text("'.ts("sum").': " + sum.toFixed(2) + " '.$btx->currency.' -- '.ts("OK").'");
              cj("#manual_match_contribution_sum").css("color", "green");
            } else {
              cj("#manual_match_contribution_sum").text("'.ts("sum").': " + sum.toFixed(2) + " '.$btx->currency.' -- '.ts("WARNING!").'");
              cj("#manual_match_contribution_sum").css("color", "red");
            }
          }

          function manual_match_create_contribution() {
            // if we have a contact to tie it to, create a test_contribution and open an editor
            var party_ba_id = "'.$btx->party_ba_id.'";
            if (party_ba_id) {
              // check if there is a contact attached
              CRM.api("BankingAccount", "getsingle", {"q": "civicrm/ajax/rest", "sequential": 1, "id": party_ba_id},
                {success: function(data) {
                  if (data.contact_id) {
                    // ok, we have a contact -> create a new (test) contribution
                    CRM.api("Contribution", "create", { "q": "civicrm/ajax/rest", "sequential": 1, 
                                                        "contact_id": data.contact_id, 
                                                        "is_test": 1, 
                                                        "total_amount": '.$btx->amount.', 
                                                        "is_pay_later": 1,
                                                        "receive_date": "'.$booking_date.'",
                                                        "currency": "'.$btx->currency.'",
                                                        "contribution_status_id": "'.$status_pending.'",
                                                        //"trxn_id": "'.$btx->bank_reference.'",
                                                        "financial_type_id": "'.$this->_plugin_config->default_financial_type_id.'"
                                                      },
                      { success: function(data) {
                        // succesfully created -> open editor
                        var contribution = data.values[0];
                        var link = cj("<div/>").html("'.$edit_contribution_link.'").text();
                        link = link.replace("__contributionid__", contribution.id);
                        link = link.replace("__contactid__", contribution.contact_id);
                        window.open(link, "_blank");

                        // also add to out list
                        manual_match_add_contribution_to_field(contribution.id);
                        manual_match_refresh_list();
                      }
                    });
                    
                  } else {
                    // no contact_id, just open an editor for a new contribution
                    manual_match_open_create_new_contribution();
                  }
                }
              });
            } else {
              // no known account, just open an editor for a new contribution
              manual_match_open_create_new_contribution();              
            }
          }

          function manual_match_open_create_new_contribution() {
            // decode the value here (idk why...)
            var link = cj("<div/>").html("'.$new_contribution_link.'").text();
            // and open it in another tab/window
            window.open(link, "_blank");            
          }

          function manual_match_add_contribution() {
            // we will try to extract an contribution id from the input field, add it to the (hidden) list of contributions and call refresh
            var value = cj("#manual_match_add").val();
            // maybe it`s only an ID:
            var cid = parseInt(value);
            if (isNaN(cid)) {
              // if not, maybe it`s a URL and we can parse the cid...
              var parts = value.split("&");
              for (part in parts) {
                if (parts[part].substring(0, 3)==="id=") {
                  cid = parseInt(parts[part].substring(3));
                  break;
                }
              }
            }

            if (isNaN(cid)) {
              alert("'.ts("No valid contribution ID given.").'");
            } else {
              // add ID to the hidden field
              manual_match_add_contribution_to_field(cid);
              cj("#manual_match_add").val("");
              manual_match_refresh_list();
            }
            return false;
          }

          function manual_match_add_contribution_to_field(contribution_id) {
            // add to field
            var list = cj("#manual_match_contributions").val().split(",");
            var index = cj.inArray(contribution_id.toString(), list);
            if (index == -1) {
              list.push(contribution_id);
              cj("#manual_match_contributions").val(list.join());
            }
          }

          function manual_match_remove_contribution(contribution_id) {
              // remove ID from the hidden field
              var list = cj("#manual_match_contributions").val().split(",");
              var index = cj.inArray(cid.toString(), list);
              if (index != -1) {
                list.splice(index, 1);
                cj("#manual_match_contributions").val(list.join());
              }
              manual_match_refresh_list();
          }
          
          // call some updates once...
          manual_match_update_sum();
          // FIXME: Take care of previous onfocus handlers
          window.onfocus=manual_match_refresh_list;
        </script>
      ';

      return $snippet;

    } else {
      return  $this->_plugin_config->ignore_message."<br/>".
              $this->_plugin_config->ignore_contribution;

    }
  }







  /**
   * check if there are more suggestions for this transaction
   */
  private function has_other_suggestions(CRM_Banking_BAO_BankTransaction $btx) {
    return count($btx->getSuggestions())>0;
  }
  
  /**
   * calculate the absolute probability based on the (possibly) relative value in the config
   */
  private function get_probability($string_value, CRM_Banking_BAO_BankTransaction $btx) {
    if (substr($string_value, -1) === "%") {
      // if the value ends in '%' it's meant to be relative to the least probable suggestion
      $least_probable = end($btx->getSuggestionList());
      if ($least_probable) {
        $least_probable_value = $least_probable->getProbability();
      } else {
        $least_probable_value = 1;
      }
      return $least_probable_value * substr($string_value, 0, strlen($string_value)-1) / 100.0;
    
    } else {
      // in the default case, we just assume it's an absolute value anyways...
      return $string_value;
    }
  }
}

