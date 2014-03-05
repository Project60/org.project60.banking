<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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
    if (!isset($config->lookup_contact_by_name)) $config->lookup_contact_by_name = array('soft_cap_probability' => 0.8, 'soft_cap_min' => 10, 'hard_cap_probability' => 0.4);
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
        $manually_processed->setProbability($this->get_probability($config->manual_probability, $btx));
        $manually_processed->setTitle($config->manual_title);
        $manually_processed->setId('manual');

        // add related contacts
        $data_parsed = $btx->getDataParsed();
        $contacts = $context->findContacts(0, $data_parsed['name'], $config->lookup_contact_by_name);
        $manually_processed->setParameter('contact_ids', implode(',', array_keys($contacts)));

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
        $cancelled_status = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Cancelled');

        foreach ($cids as $cid) {
          if ($cid) {
            $contribution = civicrm_api('Contribution', 'getsingle', array('version' => 3, 'id' => $cid));
            if ($contribution['is_error']) {
              CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contribution #%s"), $cid), ts('Error'), 'error');
              continue;
            }

            // save the account
            if (!empty($contribution['contact_id'])) {
              $this->storeAccountWithContact($btx, $contribution['contact_id']);
            }

            $query = array('version' => 3, 'id' => $cid);
            $query['is_test'] = 0;
            $query = array_merge($query, $this->getPropagationSet($btx, 'contribution'));   // add propagated values

            // set status to completed, unless it's a negative amount...
            if ($btx->amount < 0) {
              // ...in this case, we want to cancel this
              $query['contribution_status_id'] = $cancelled_status;
              $query['cancel_date'] = date('YmdHis', strtotime($btx->booking_date));
            } else {
              // ...otherwise, we close it
              $query['contribution_status_id'] = $completed_status;
              $query['receive_date'] = date('YmdHis', strtotime($btx->booking_date));
            }

            $result = civicrm_api('Contribution', 'create', $query);
            if (isset($result['is_error']) && $result['is_error']) {
              CRM_Core_Session::setStatus(sprintf(ts("Couldn't modify contribution #%s"), $cid), ts('Error'), 'error');
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

      } else {
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
      $contact_ids = $match->getParameter('contact_ids');
      $data_parsed = $btx->getDataParsed();
      $booking_date = date('YmdHis', strtotime($btx->booking_date));
      $status_pending = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Pending');
      $ts_completed = ts("Completed");
      $ts_cancelled = ts("<br/><b>Will be cancelled.</b>");
      $new_contribution_link = CRM_Utils_System::url("civicrm/contribute/add", "reset=1&action=add&context=standalone");
      $edit_contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=update&reset=1&id=__contributionid__&cid=__contactid__&context=home");
      $view_contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id=__contributionid__&cid=__contactid__&context=home");
      $view_contact_link = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid=__contactid__");
      $view_search_link = CRM_Utils_System::url("civicrm/contact/search", "reset=1");

      // get propagated data for contributions
      $contribution_propagated_data = '';
      foreach ($this->getPropagationSet($btx, 'contribution') as $key => $value) {
        $contribution_propagated_data .= '"'.$key.'": "'.$value.'",';
      }

      $snippet  = "<div>" . ts("Please manually process this payment and <i>then</i> add the resulting contributions to this list, <b><i>before</i></b> confirming this option.");
      $snippet .= "<input type=\"hidden\" id=\"manual_match_contributions\" name=\"manual_match_contributions\" value=\"\"/></div>";    // this will hold the list of contribution ids
      $snippet .= "<input type=\"hidden\" id=\"manual_match_contacts\" name=\"manual_match_contacts\" value=\"$contact_ids\"/></div>";    // this will hold the list of contact ids

      // add contact level
      $snippet .= "<br/><a class=\"button\" onclick=\"manual_match_create_contribution();\"><span><div class=\"icon add-icon\"></div>" . ts("add new contribution for:") . "</span></a>";
      $snippet .= "<select style=\"float:left;\" id=\"manual_match_contact_selector\"></select>";
      $snippet .= "<div onclick=\"manual_match_show_selected_contact();\" class=\"ui-icon ui-icon-circle-arrow-e\" style=\"float:left;\"></div>";
      $snippet .= "<div style=\"float:left;\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_add_contact();\"><span><div class=\"icon add-icon\"></div>" . ts("add contact ID to list") . ":</span></a>";
      $snippet .= "<input id=\"manual_match_add_contact_input\" onkeydown=\"if (event.keyCode == 13) return manual_match_add_contact();\" type=\"text\" style=\"width: 4em; height: 1.4em;\"></input>";

      // add contribution level
      $snippet .= "<br/><br/><div>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_refresh_list();\"><span><div class=\"icon refresh-icon\"></div>" . ts("refresh") . "</span></a>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_open_create_new_contribution();\"><span><div class=\"icon add-icon\"></div>" . ts("add empty contribution") . "</span></a>";
      $snippet .= "<a class=\"button\" onclick=\"manual_match_add_contribution();\"><span><div class=\"icon add-icon\"></div>" . ts("add existing contribution by ID") . ":</span></a>";
      $snippet .= "<input id=\"manual_match_add\" onkeydown=\"if (event.keyCode == 13) return manual_match_add_contribution();\" type=\"text\" style=\"width: 4em; height: 1.4em;\"></input>";
      $snippet .= "<div style=\"float:right;\">";
      $snippet .= "<span id=\"manual_match_contribution_sum\" align=\"right\" style=\"color: red; font-weight: bold;\"><b>".ts("sum").": 0.00 EUR</b></span>";
      $snippet .= "</div></div>";

      // add cancellation warning
      if ($btx->amount < 0)
        $snippet .= "<br/><div>".ts("<strong>WARNING:</strong> This is a negative amount, so all contributions below will be <strong>cancelled</strong>.")."</div>";  

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
      
      // append the java script contributing the functionality
      $snippet .= '
        <script type="text/javascript">
          /** 
           * refresh the table showing the related contributions 
           */
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

          /** 
           * Loads a contact into to the option list. 
           * It also triggers loading the next id from the list in the hidden field 
           */
          function manual_match_load_contact_into_contact_list(contact_id, select=false) {
            CRM.api("Contact", "get", {"q": "civicrm/ajax/rest", "sequential": 1, "id": contact_id},
                { success: function(data) {
                  if (data.count > 0) {
                    // generate contact select option
                    var contact = data.values[0];
                    var item_label = contact.display_name;
                    if (contact.street_address || contact.city) {
                      item_label += " (" + contact.street_address + ", " + contact.city + ")";
                    } else {
                      item_label += " ('.ts("unknown address").')";
                    }
                    if (select) {
                      var item = "<option selected =\"true\" value=\""+ contact.id + "\">"+item_label+"</option>";
                    } else {
                      var item = "<option value=\""+ contact.id + "\">"+item_label+"</option>";
                    }

                    // remove dummy if still in there...
                    cj("#manual_match_contact_selector option[value=0]").remove();

                    // ...add to selector list
                    cj("#manual_match_contact_selector").append(item);

                    // finally, trigger the next contact to be loaded
                    var list = cj("#manual_match_contacts").val().split(",");
                    var index = cj.inArray(contact_id.toString(), list);
                    if (index != -1 && (index+1) < list.length) {
                      manual_match_load_contact_into_contact_list(list[index+1], select);
                    }
                  } else {
                    alert("Conact not found!");
                  }
                }
              });
          }

          /** 
           * create/refresh the table showing the related contacts 
           */
          function manual_match_create_contact_list() {
            // clear the options
            cj("#manual_match_contact_selector").empty();
            var dummy_item = "<option value=\"0\">'.ts("No contact found... add manually &nbsp;&nbsp;&nbsp;=>").'</option>";
            cj("#manual_match_contact_selector").append(dummy_item);

            var list = cj("#manual_match_contacts").val().split(",");
            if (list.length > 0 && list[0]) {
              manual_match_load_contact_into_contact_list(list[0], false);
            }
          }

          /** 
           * append the given contribution data set to the contribution list 
           */
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
                row += "<td><a href=\"#\" onclick=\"manual_match_remove_contribution(" + contribution.id + ");\">['.ts('remove').']</a>";
                row += "&nbsp;<a href=\"" + view_link + "\" target=\"_blank\">['.ts('view').']</a></td>";
                row += "<td>" + contribution.display_name + "</td>";
                row += "<td>" + contribution.financial_type + "</td>";
                row += "<td>" + contribution.receive_date.replace(" 00:00:00","");  + "</td>";
                if (contribution.contribution_status != "'.$ts_completed.'") {
                  row += "<td >" + contribution.contribution_status + "</td>";
                } else {
                  // if this a cancellation, mark it:
                  if (parseFloat('.$btx->amount.') < 0) {
                    row += "<td>" + contribution.contribution_status + "'.$ts_cancelled.'</td>";
                  } else {
                    row += "<td style=\"color: red;\"><b>" + contribution.contribution_status + "</b></td>";
                  }
                }
                row += "<td name=\"amount\" align=\"right\">" + parseFloat(contribution.total_amount).toFixed(2) + " " + contribution.currency + "</td>";
                row += "</tr>";
                cj("#manual_match_contribution_table").append(row);
                manual_match_update_sum();
              }
            }
          }

          /** 
           * update the sum field, showing the total of all related contribtions
           */
          function manual_match_update_sum() {
            // sum up the rows
            var sum = 0.0;
            cj("#manual_match_contribution_table tr").each(function() {
              sum += parseFloat(cj("td[name=amount]", this).text());
            });

            // update the style...
            if (sum == Math.abs(parseFloat('.$btx->amount.'))) {
              cj("#manual_match_contribution_sum").text("'.ts("sum").': " + sum.toFixed(2) + " '.$btx->currency.' -- '.ts("OK").'");
              cj("#manual_match_contribution_sum").css("color", "green");
            } else {
              cj("#manual_match_contribution_sum").text("'.ts("sum").': " + sum.toFixed(2) + " '.$btx->currency.' -- '.ts("WARNING!").'");
              cj("#manual_match_contribution_sum").css("color", "red");
            }
          }

          /** 
           * create a new contribution with the selected contact
           */
          function manual_match_create_contribution() {
            // get selected contact
            var contact_id = cj("#manual_match_contact_selector").val();
            if (!contact_id) {
              // TODO: set/translate message
              alert("No ID set!");
              return;
            }
            // ok, we have a contact -> create a new (test) contribution
            CRM.api("Contribution", "create", { "q": "civicrm/ajax/rest", "sequential": 1, 
                                                '.$contribution_propagated_data.'
                                                "contact_id": contact_id, 
                                                "is_test": 1, 
                                                "total_amount": parseFloat('.$btx->amount.').toFixed(2), 
                                                "is_pay_later": 1,
                                                "receive_date": "'.$booking_date.'",
                                                "currency": "'.$btx->currency.'",
                                                "contribution_status_id": "'.$status_pending.'",
                                                //"trxn_id": "'.$btx->bank_reference.'",
                                                "source": "'.$this->_plugin_config->manual_default_source.'",
                                                "financial_type_id": "'.$this->_plugin_config->manual_default_financial_type_id.'"
                                              },
              { success: function(data) {
                var contribution = data.values[0];

                // succesfully created -> add to our list
                manual_match_add_contribution_to_field(contribution.id);
                manual_match_refresh_list();

                // also open editor
                var link = cj("<div/>").html("'.$edit_contribution_link.'").text();
                link = link.replace("__contributionid__", contribution.id);
                link = link.replace("__contactid__", contribution.contact_id);
                window.open(link, "_blank");
              }
            });                    
          }

          /** 
           * open a create contribution dialogue. Unfortunately it is not possible
           * to automatically add this contribution to the list.
           */
          function manual_match_open_create_new_contribution() {
            // decode the value here (idk why...)
            var link = cj("<div/>").html("'.$new_contribution_link.'").text();
            // and open it in another tab/window
            window.open(link, "_blank");            
          }

          /** 
           * triggered when the user wants to manually add a contribution as related
           */
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

          /**
           * triggered when "add contact to list" button is pressed.
           * will add the content of the item to the list of selectable contact
           */
          function manual_match_add_contact() {
            // we will try to extract an contact id from the input field, add it to the (hidden) list of contributions
            var value = cj("#manual_match_add_contact_input").val();
            // maybe it`s only an ID:
            var contact_id = parseInt(value);
            if (isNaN(contact_id)) {
              // if not, maybe it`s a URL and we can parse the contact_id...
              var parts = value.split("&");
              for (part in parts) {
                if (parts[part].substring(0, 4)==="cid=") {
                  contact_id = parseInt(parts[part].substring(4));
                  break;
                }
              }
            }

            if (isNaN(contact_id)) {
              alert("'.ts("No valid contact ID given.").'");
            } else {
              // add ID to the hidden field
              var list = cj("#manual_match_contacts").val().split(",");
              var index = cj.inArray(contact_id.toString(), list);
              if (index == -1) {
                //list.splice(0, 0, contact_id.toString());   // insert at beginning
                list.push(contact_id.toString());
                cj("#manual_match_contacts").val(list.join());
  
                // load the contact and add to the selection
                manual_match_load_contact_into_contact_list(contact_id, true);
              }
            }
            
            cj("#manual_match_add_contact_input").val("");            
            return false;
          }

          /** 
           * will add the given contribution_id to the (hidden) input field
           */
          function manual_match_add_contribution_to_field(contribution_id) {
            // add to field
            var list = cj("#manual_match_contributions").val().split(",");
            var index = cj.inArray(contribution_id.toString(), list);
            if (index == -1) {
              list.push(contribution_id);
              cj("#manual_match_contributions").val(list.join());
            }
          }

          /** 
           * will remove the given contribution from the list of related contributions
           */
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

          /** 
           * will open a contact view with the selected panel
           */
          function manual_match_show_selected_contact() {
              // get contact_id from selector
              var contact_id = cj("#manual_match_contact_selector").val();
              if (parseInt(contact_id) > 0) {
                var link = cj("<div/>").html("'.$view_contact_link.'").text();
                link = link.replace("__contactid__", contact_id);
              } else {
                var link = cj("<div/>").html("'.$view_search_link.'").text();
              }
              // open link
              window.open(link, "_blank");
          }
          
          // call some updates once...
          manual_match_update_sum();
          manual_match_create_contact_list();

          // FIXME: Take care of previous onfocus handlers
          window.onfocus=manual_match_refresh_list;
        </script>
      ';

      return $snippet;

    } else {
      return  $this->_plugin_config->ignore_message;

    }
  }

  /** 
   * Generate html code to visualize the executed match.
   * 
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */  
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    if ($match->getId()==="manual") {
      $cids = $match->getParameter('contribution_ids');
      $text = "<p>".ts("This payment was manually matched to the following contributions:")."<ul>";
      foreach ($cids as $contribution_id) {
        if ($contribution_id) {
          $contribution_link = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id=$contribution_id&cid=2&context=home");
          $text .= "<li><a href=\"$contribution_link\">".ts("Contribution")." #$contribution_id</a>";          
        }
      }
      $text .=  "</ul>";
      return $text;
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
      $suggestion_list = $btx->getSuggestionList();
      $least_probable = end($suggestion_list);
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

