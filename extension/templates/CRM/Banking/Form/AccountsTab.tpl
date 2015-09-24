{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

{if $bank_accounts}
<div>
  <table id="contact-activity-selector-dashlet">
  <thead>
    <tr>
      <th><div>{ts}Bank Account{/ts}</div></th>
      <th><div>{ts}Bank{/ts}</div></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    {foreach from=$bank_accounts item=account}
    <tr class="{cycle values="odd,even"}">      
      <td>
        <table style="border: 0;">
        {foreach from=$account.references item=reference name=account_reference}
          <tr><td>
            {if $reference.reference_type eq 'NBAN_DE'}
            {assign var=german value="/"|explode:$reference.reference} 
            ({ts}German{/ts})&nbsp;&nbsp;&nbsp;BLZ:&nbsp;{$german.0}&nbsp;&nbsp;&nbsp;Kontonummer:&nbsp;{$german.1}
            {elseif $reference.reference_type eq 'ENTITY'}
            {* We hide entity references for the moment *}
            {else}
            <span title="{$reference.reference_type_label}">{$reference.reference}&nbsp;({$reference.reference_type})</span>
            {/if}
            <a onClick="banking_deletereference({$account.id}, {$reference.id});" class="action-item action-item-first" title="{ts}delete{/ts}">{ts}[-]{/ts}</a>
            {if $smarty.foreach.account_reference.last}
            <a onClick="banking_addreference({$account.id});" class="action-item action-item-first" title="{ts}add{/ts}">{ts}[+]{/ts}</a>
            {/if}
          </td></tr>
          {/foreach}
        </table>
      </td>
      <td>
        <table style="border: 0;">
        {foreach from=$account.data_parsed item=value key=key}
          <tr>
            <td style="width: 130px;"><b>
              {if $key eq 'bank' or $key eq 'name'}
                {ts}Bank Name{/ts}
              {elseif $key eq 'country'}
                {ts}Country{/ts}
              {else}
                {$key}
              {/if}
            </b></td>
            <td>{$value}</td>
          </tr>
          {/foreach}
        </table>
      </td>
      <td style="vertical-align: middle;">
        <a title="{ts}Delete{/ts}" class="delete button" onClick="banking_deleteaccount({$account.id});">
          <span><div class="icon delete-icon ui-icon-trash"></div>{ts}Delete{/ts}</span>
        </a>
        <a title="{ts}Edit{/ts}" class="edit button" onClick="banking_editaccount({$account.id});">
          <span><div class="icon edit-icon ui-icon-edit"></div>{ts}Edit{/ts}</span>
        </a>
      </td>
    </tr>
    {/foreach}
  </tbody>
  </table>
</div>
{else}
<h3>{ts}This contact has no known accounts associated with him/her.{/ts}</h3>
{/if}

<a id="banking_account_addbtn" title="{ts}Add{/ts}" class="add button" onClick="banking_addaccount();">
  <span><div class="icon add-icon ui-icon-add"></div>{ts}Add{/ts}</span>
</a>

<div id="banking_account_form" hidden="1">
  <h3>{ts}Add Bank Account{/ts}</h3>
  <div class="crm-section">
    <div class="label">{$form.reference_type.label}</div>
    <div class="content">{$form.reference_type.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="content" id="reference_description"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.reference.label}</div>
    <div class="content">{$form.reference.html}</div>
    <div class="clear"></div>
  </div>
  <hr/>
  <div>{$form.contact_id.html}</div>
  <div>{$form.reference_id.html}</div>
  <div>{$form.ba_id.html}</div>
  <div class="crm-section">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.bank_name.label}</div>
    <div class="content">{$form.bank_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.country.label}</div>
    <div class="content">{$form.country.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>



<script type="text/javascript">
var bank_accounts   = {$bank_accounts_json};
var reference_types = {$reference_types_json};
{literal}

// divert Cancel button
cj("input.cancel").click(function() {
  cj("#banking_account_addbtn").show();
  cj("#banking_account_form").hide();
  cj("#bank_name").val('');
  cj("#bic").val('');
  cj("#country").val('');
  cj("input[name='ba_id']").val('');
  cj("input[name='reference_id']").val('');
  cj("#reference").val('');
  cj("#reference_type").val('');
  return false;  
})

// show description of reference type
cj("#reference_type").change(function() {
  var type_id = cj(this).val();
  cj("#reference_description").text('');
  if (reference_types[type_id].description) {
    cj("#reference_description").prepend(reference_types[type_id].description);
  }
});
cj("#reference_type").trigger('change');

/** JS function for creating a bank account */
function banking_addaccount() {
  cj("#banking_account_addbtn").hide();
  cj("#banking_account_form").show();
  cj("input[name='ba_id']").val('');
  cj("input[name='reference_id']").val('');
}

/** JS function for editing a bank account */
function banking_editaccount(ba_id) {
  cj("#banking_account_addbtn").hide();
  cj("#banking_account_form").show();
  cj("#bank_name").val(bank_accounts[ba_id].data_parsed.name);
  cj("#bic").val(bank_accounts[ba_id].data_parsed.BIC);
  cj("#country").val(bank_accounts[ba_id].data_parsed.country);
  cj("input[name='ba_id']").val(ba_id);

  cj("input[name='reference_id']").val(bank_accounts[ba_id].references[0].id);
  cj("#reference").val(bank_accounts[ba_id].references[0].reference);
  cj("#reference_type").val(bank_accounts[ba_id].references[0].reference_type);
}

/** JS function for adding a bank account reference */
function banking_addreference(ba_id) {
  cj("#banking_account_addbtn").hide();
  cj("#banking_account_form").show();
  cj("#bank_name").val(bank_accounts[ba_id].data_parsed.name);
  cj("#bic").val(bank_accounts[ba_id].data_parsed.BIC);
  cj("#country").val(bank_accounts[ba_id].data_parsed.country);
  cj("input[name='ba_id']").val(ba_id);
  cj("input[name='reference_id']").val('');
  cj("#reference").val('');
}

/** JS function for deleting a bank account */
function banking_deleteaccount(ba_id) {
  CRM.confirm(function() {
    CRM.api('BankingAccount', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': ba_id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          CRM.alert("{/literal}{ts}The bank account has been deleted{/ts}", "{ts}Success{/ts}{literal}", "success");
          var contentId = cj('#tab_bank_accounts').attr('aria-controls');
          cj('#' + contentId).load(CRM.url('civicrm/banking/accounts_tab', {'reset': 1, 'snippet': 1, 'force': 1, 'cid':{/literal}{$contact_id}{literal}}));
        }else{
          CRM.alert("{/literal}" + data['error_message'], "{ts}Error{/ts}{literal}", "error");
        }
      }
    }
  );
  },
  {
    message: {/literal}"{ts}Are you sure you want to delete this bank account?{/ts}"{literal}
  });
}

/** JS function for deleting a bank account */
function banking_deletereference(ba_id, ref_id) {
  if (bank_accounts[ba_id].references.length < 2) {
    CRM.alert("{/literal}{ts}A bank account has to have at least one reference.{/ts}", "{ts}Failed{/ts}{literal}", "warning");
    return;
  }

  CRM.confirm(function() {
    CRM.api('BankingAccountReference', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': ref_id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          CRM.alert("{/literal}{ts}The bank account reference has been deleted{/ts}", "{ts}Success{/ts}{literal}", "success");
          var contentId = cj('#tab_bank_accounts').attr('aria-controls');
          cj('#' + contentId).load(CRM.url('civicrm/banking/accounts_tab', {'reset': 1, 'snippet': 1, 'force': 1, 'cid':{/literal}{$contact_id}{literal}}));
        }else{
          CRM.alert("{/literal}" + data['error_message'], "{ts}Error{/ts}{literal}", "error");
        }
      }
    }
  );
  },
  {
    message: {/literal}"{ts}Are you sure you want to delete this bank account reference?{/ts}"{literal}
  });
}

{/literal}
</script>