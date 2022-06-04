{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*}

{capture assign=view_contribution_link}{crmURL p="civicrm/contact/view/contribution" q="action=view&reset=1&id=__contributionid__&cid=__contactid__&context=home"}{/capture}
{capture assign=edit_contribution_link}{crmURL p="civicrm/contact/view/contribution" q="action=update&reset=1&id=__contributionid__&cid=__contactid__&context=home"}{/capture}
{capture assign=new_contribution_link}{crmURL p="civicrm/contribute/add" q="reset=1&action=add&context=standalone"}{/capture}
{capture assign=view_contact_link}{crmURL p="civicrm/contact/view" q="reset=1&cid=__contactid__"}{/capture}
{capture assign=view_search_link}{crmURL p="civicrm/contact/search" q="reset=1"}{/capture}


{if $mode == 'ignore'}    {* MANUAL IGNORE *}
  {$ignore_message}


{else}                    {* MANUAL RECONCILIATION *}
<div>
  {ts domain='org.project60.banking'}Please manually process this transaction and <i>then</i> add the resulting contributions to this list, <b><i>before</i></b> confirming this option.{/ts}
  <input type="hidden" id="manual_match_contributions" name="manual_match_contributions" value=""/>
  <input type="hidden" id="manual_match_contacts" name="manual_match_contacts" value="{$contact_ids}"/>
</div>

<br/>
  <a class="button" onclick="manual_match_create_contribution();"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts domain='org.project60.banking'}add new contribution for:{/ts}</span></a>
  <select style="float:left;" id="manual_match_contact_selector"></select>
  <div onclick="manual_match_show_selected_contact();" class="ui-icon ui-icon-circle-arrow-e" style="float:left;"></div>
  <div style="float:left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
  <div style="display: inline-block;"><a class="button" onclick="manual_match_add_contact();"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts domain='org.project60.banking'}add contact ID to list{/ts}:</span></a>
  <input id="manual_match_add_contact_input" onkeydown="if (event.keyCode == 13) return manual_match_add_contact();" type="text" style="width: 4em; height: 1.4em;"></input>
</div>

<br/><br/>

<div>
  <a class="button" onclick="manual_match_refresh_list();"><span><div class="icon refresh-icon ui-icon-refresh"></div>{ts domain='org.project60.banking'}refresh{/ts}</span></a>
  <a class="button" onclick="manual_match_open_create_new_contribution();"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts domain='org.project60.banking'}create empty contribution{/ts}</span></a>
  <a class="button" onclick="manual_match_add_contribution();"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts domain='org.project60.banking'}add existing contribution by ID{/ts}</span></a>
  <input id="manual_match_add" onkeydown="if (event.keyCode == 13) return manual_match_add_contribution();" type="text" style="width: 4em; height: 1.4em;"></input>
  <div style="float:right;">
    <span id="manual_match_contribution_sum" align="right" style="color: red; font-weight: bold;"><b>{ts domain='org.project60.banking'}sum{/ts}: 0.00 EUR</b></span>
  </div>
</div>

{if $btx.amount lt 0}
<br/><div>{ts domain='org.project60.banking'}<strong>WARNING:</strong> This is a negative amount, so all contributions below will be <strong>cancelled</strong>.{/ts}</div>
{/if}

<br/>
<table>
  <th></th>
  <th>{ts domain='org.project60.banking'}Contact{/ts}</th>
  <th>{ts domain='org.project60.banking'}Type{/ts}</th>
  <th>{ts domain='org.project60.banking'}Date{/ts}</th>
  <th>{ts domain='org.project60.banking'}Status{/ts}</th>
  <th align="right">{ts domain='org.project60.banking'}Amount{/ts}</th>
  <tbody id="manual_match_contribution_table">
  <tr class="manual-match-placeholder">
    <td colspan="5" align="center" style="font-size: larger; padding: 1em;">
      <span>{ts domain='org.project60.banking'}DROP ANY CONTRIBUTION LINK IN HERE TO RECONCILE{/ts}</span>
    </td>
  </tr>
  </tbody>
</table>





{* MANUAL CONTRIBUTION MATCHER JAVASCRIPT FUNCTIONS *}
<script type="text/javascript">
  let contact_ids2probability = {$contact_ids2probability};
  let injected_contribution_ids = "{$injected_contribution_ids}".split(',');
  let contribution_ids_injected = false;

  {literal}
  
  // add 'refresh list' action after all AJAX calls
  cj(document).on('crmPopupClose', manual_match_refresh_list);
  cj(document).on('crmPopupFormSuccess', manual_match_refresh_list);
  cj(document).on('crmFormSuccess', manual_match_refresh_list);

  if (injected_contribution_ids) {
    cj(document).ready(function() {
      if (!contribution_ids_injected) {
        if (cj("#manual_match_contributions")) {
          contribution_ids_injected = true;
          cj("#manual_match_contributions").val(injected_contribution_ids.join());
          manual_match_refresh_list();
        }
      }
    });
  }

  /** 
   * refresh the table showing the related contributions 
   */
  function manual_match_refresh_list() {
    // clear the table
    cj("#manual_match_contribution_table tr.manual-match-placeholder").show();
    cj("#manual_match_contribution_table tr.manual-match-contribution").remove();

    // then rebuild with the cids in the list
    let list = cj("#manual_match_contributions").val().split(",");
    for (cid_idx in list) {
      let cid = parseInt(list[cid_idx]);
      if (!isNaN(cid) && cid>0) {
        // load the contribution
        CRM.api3("Contribution", "get", {
            "id": cid,
            "sequential": 1,
            "return": "{/literal}{$manual_contribution_get_return_params}{literal}"
            },
            { success: manual_match_add_data_to_list }
        );
      }
    }
  }

  /** 
   * Loads a contact into to the option list. 
   * It also triggers loading the next id from the list in the hidden field 
   */
  function manual_match_load_contact_into_contact_list(contact_id, select) {
    CRM.api3("Contact", "get", {"sequential": 1, "id": contact_id},
        { success: function(data) {
          if (data.count > 0) {
            // generate contact select option
            var contact = data.values[0];

            // generate precision indicator
            var percent = 1.0; // default value
            if (contact.id in contact_ids2probability)
              percent = contact_ids2probability[contact.id];
            percent_string = Math.floor(percent * 100.0) + "%";
            if (percent < 0.1) {
              percent_string = "0" + percent_string;
            }

            // generate option label
            var item_label = "(" + percent_string + ") " + contact.display_name + " [" + contact.id + "]";
            if (contact.street_address || contact.city) {
              item_label += " (" + contact.street_address + ", " + contact.city + ")";
            } else {
              item_label += " ({/literal}{ts domain='org.project60.banking'}unknown address{/ts}{literal})";
            }
            hook_label = cj(document).triggerHandler('banking_contact_option_element', [item_label, contact]);
            if (typeof hook_label != 'undefined') {
              item_label = hook_label;
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
          
          } else {
            alert("Contact [" + contact_id + "] not found!");

          }

          // finally, trigger the next contact to be loaded
          var list = cj("#manual_match_contacts").val().split(",");
          var index = -1;

          // find last index of contact_id (to prevent loops)
          for (var i = list.length - 1; i >= 0; i--) {
            if (list[i] == contact_id.toString()) {
              index = i;
              break;
            }
          };

          // load next if there is one
          if (index != -1 && (index+1) < list.length) {
            manual_match_load_contact_into_contact_list(list[index+1], select);
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
    var dummy_item = "<option value=\"0\">{/literal}{ts domain='org.project60.banking'}No contact found... add manually &nbsp;&nbsp;&nbsp;=>{/ts}{literal}</option>";
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
      cj("#manual_match_contribution_table tr.manual-match-placeholder").hide();
      var contribution = data.values[0];
      manual_match_add_contribution_to_field(contribution.id);

      // add to table, if not already there
      if (!cj("#manual_match_row_cid_" + contribution.id).length) {
        let view_link = cj("<div/>").html("{/literal}{$view_contribution_link}{literal}").text();
        view_link = view_link.replace("__contributionid__", contribution.id);
        view_link = view_link.replace("__contactid__", contribution.contact_id);

        let row = "<tr class=\"manual-match-contribution\" id=\"manual_match_row_cid_" + contribution.id + "\">";
        row += "<td><a onclick=\"manual_match_remove_contribution(" + contribution.id + ");\">[{/literal}{ts domain='org.project60.banking'}remove{/ts}{literal}]</a>";
        row += "&nbsp;<a href=\"" + view_link + "\" target=\"_blank\" class=\"crm-popup\">[{/literal}{ts domain='org.project60.banking'}view{/ts}{literal}]</a></td>";
        row += "<td>" + contribution.display_name + "</td>";
        row += "<td>" + contribution.financial_type + "</td>";
        row += "<td>" + contribution.receive_date.replace(" 00:00:00","");  + "</td>";
        if (contribution.contribution_status != "{/literal}{ts domain='org.project60.banking'}Completed{/ts}{literal}") {
          row += "<td >" + contribution.contribution_status + "</td>";
        } else {
          // if this a cancellation, mark it:
          if (parseFloat({/literal}{$btx.amount}{literal}) < 0) {
            row += "<td>" + contribution.contribution_status + "{/literal}{ts domain='org.project60.banking'}<br/><b>Will be cancelled.</b>{/ts}{literal}</td>";
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
      sum += parseFloat(cj("td[name=amount]", this).text()) || 0;
    });

    // update the style...
    if (sum == Math.abs(parseFloat({/literal}{$btx.amount}{literal}))) {
      cj("#manual_match_contribution_sum").text("{/literal}{ts domain='org.project60.banking'}sum{/ts}: " + sum.toFixed(2) + " {$btx.currency} -- {ts domain='org.project60.banking'}OK{/ts}{literal}");
      cj("#manual_match_contribution_sum").css("color", "green");
    } else {
      cj("#manual_match_contribution_sum").text("{/literal}{ts domain='org.project60.banking'}sum{/ts}: " + sum.toFixed(2) + " {$btx.currency} -- {ts domain='org.project60.banking'}WARNING{/ts}{literal}");
      cj("#manual_match_contribution_sum").css("color", "red");
    }
  }

  /** 
   * create a new contribution with the selected contact
   */
  function manual_match_create_contribution() {
    // get selected contact
    let contact_id = cj("#manual_match_contact_selector").val();
    if (!contact_id) {
      // TODO: set/translate message
      alert("No ID set!");
      return;
    }
    // ok, we have a contact -> create a new (test) contribution
    CRM.api3("Contribution", "create", { "sequential": 1,
                                        "contact_id": contact_id, 
                                        "is_test": 1, 
                                        "total_amount": parseFloat({/literal}{$btx.amount}{literal}).toFixed(2), 
                                        "is_pay_later": 1,
                                        "receive_date": "{/literal}{$booking_date}{literal}",
                                        "currency": "{/literal}{$btx.currency}{literal}",
                                        "contribution_status_id": "{/literal}{$status_pending}{literal}",
                                        "source": "{/literal}{$manual_default_source}{literal}",
                                        "financial_type_id": "{/literal}{$manual_default_financial_type_id}{literal}",
                                        {/literal}{foreach from=$create_propagation item=value key=key}
                                        "{$key}": "{$value}",
                                        {/foreach}{literal}
                                      },
      { success: function(data) {
        let contribution = data.values[0];

        // succesfully created -> add to our list
        manual_match_add_contribution_to_field(contribution.id);
        manual_match_refresh_list();

        // ...also open editor
        banking_open_link("{/literal}{$edit_contribution_link}{literal}", {"__contributionid__":contribution.id, "__contactid__":contribution.contact_id}, true);
      }
    });                    
  }

  /** 
   * open a create contribution dialogue. Unfortunately it is not possible
   * to automatically add this contribution to the list.
   */
  function manual_match_open_create_new_contribution() {
    banking_open_link("{/literal}{$new_contribution_link}{literal}", {}, false);
  }

  /** 
   * triggered when the user wants to manually add a contribution as related
   */
  function manual_match_add_contribution() {
    // we will try to extract an contribution id from the input field, add it to the (hidden) list of contributions and call refresh
    let value = cj("#manual_match_add").val();
    // maybe it`s only an ID:
    let cid = parseInt(value);
    if (isNaN(cid)) {
      // if not, maybe it`s a URL and we can parse the cid...
      let parts = value.split("&");
      for (part in parts) {
        if (parts[part].substring(0, 3)==="id=") {
          cid = parseInt(parts[part].substring(3));
          break;
        }
      }
    }

    if (isNaN(cid)) {
      alert("{/literal}{ts domain='org.project60.banking'}No valid contribution ID given.{/ts}{literal}");
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
    let value = cj("#manual_match_add_contact_input").val();
    // maybe it`s only an ID:
    let contact_id = parseInt(value);
    if (isNaN(contact_id)) {
      // if not, maybe it`s a URL and we can parse the contact_id...
      let parts = value.split("&");
      for (part in parts) {
        if (parts[part].substring(0, 4)==="cid=") {
          contact_id = parseInt(parts[part].substring(4));
          break;
        }
      }
    }

    if (isNaN(contact_id)) {
      alert("{/literal}{ts domain='org.project60.banking'}No valid contribution ID given.{/ts}{literal}");
    } else {
      // add ID to the hidden field
      let list = cj("#manual_match_contacts").val().split(",");
      let index = cj.inArray(contact_id.toString(), list);
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
    let list = cj("#manual_match_contributions").val().split(",");
    let index = cj.inArray(contribution_id.toString(), list);
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
      console.log("remove " + contribution_id);
      let list = cj("#manual_match_contributions").val().split(",");
      let index = cj.inArray(cid.toString(), list);
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
      let contact_id = cj("#manual_match_contact_selector").val();
      if (parseInt(contact_id) > 0) {
        banking_open_link("{/literal}{$view_contact_link}{literal}", {"__contactid__":contact_id}, false);
      } else {
        banking_open_link("{/literal}{$view_search_link}{literal}", {}, false);
      }
  }

  // add drop handler
  cj("#manual_match_contribution_table").on('dragover dragenter', function(e) {
      // stop default handlers
      e.preventDefault();
      e.stopPropagation();
    });
  cj(document).on('drop', function(e) {
      cj("#manual_match_add").val(e.originalEvent.dataTransfer.getData('Text'));
      manual_match_add_contribution();
      e.preventDefault();
  });

  // call some updates once...
  manual_match_update_sum();
  manual_match_create_contact_list();

  // FIXME: Take care of previous onfocus handlers
  window.onfocus=manual_match_refresh_list;

{/literal}
</script>

{/if}