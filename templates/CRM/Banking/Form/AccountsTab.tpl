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

{* check for the org.project60.bic extension *}
{crmAPI var='bic_extension_check' entity='Bic' action='findbyiban' q='civicrm/ajax/rest' bic='TEST'}
{capture assign=bic_extension_installed}{if $bic_extension_check.is_error eq 0}1{/if}{/capture}

{if $bank_accounts}
<div>
  <table id="contact-activity-selector-dashlet">
  <thead>
    <tr>
      <th><div>{ts domain='org.project60.banking'}Bank Account{/ts}</div></th>
      <th><div>{ts domain='org.project60.banking'}Bank{/ts}</div></th>
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
            BLZ:&nbsp;{$german.0}&nbsp;&nbsp;&nbsp;Kto:&nbsp;{$german.1}&nbsp;({ts domain='org.project60.banking'}German{/ts})
            {elseif $reference.reference_type eq 'ENTITY'}
            {* We hide entity references for the moment *}
            {else}
            <span title="{$reference.reference_type_label}">{$reference.reference}&nbsp;({$reference.reference_type})</span>
            {/if}
            {if $account.references|@count gt 1}
            <a onClick="banking_deletereference({$account.id}, {$reference.id});" class="action-item action-item-first" title="{ts domain='org.project60.banking'}delete{/ts}">{ts domain='org.project60.banking'}[-]{/ts}</a>
            {/if}
            {if $smarty.foreach.account_reference.last}
            <a onClick="banking_addreference({$account.id});" class="action-item action-item-first" title="{ts domain='org.project60.banking'}add{/ts}">{ts domain='org.project60.banking'}[+]{/ts}</a>
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
                {ts domain='org.project60.banking'}Account Name{/ts}
              {elseif $key eq 'country'}
                {ts domain='org.project60.banking'}Country{/ts}
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
        <a title="{ts domain='org.project60.banking'}Delete{/ts}" class="delete button" onClick="banking_deleteaccount({$account.id});">
          <span><div class="icon delete-icon ui-icon-trash"></div>{ts domain='org.project60.banking'}Delete{/ts}</span>
        </a>
        <a title="{ts domain='org.project60.banking'}Edit{/ts}" class="edit button" onClick="banking_editaccount({$account.id});">
          <span><div class="icon edit-icon ui-icon-pencil"></div>{ts domain='org.project60.banking'}Edit{/ts}</span>
        </a>
      </td>
    </tr>
    {/foreach}
  </tbody>
  </table>
</div>
{else}
<h3>{ts domain='org.project60.banking'}This contact has no known accounts associated with him/her.{/ts}</h3>
{/if}

<a id="banking_account_addbtn" title="{ts domain='org.project60.banking'}Add{/ts}" class="add button" onClick="banking_addaccount();">
  <span><div class="icon add-icon ui-icon-add ui-icon-circle-plus"></div>{ts domain='org.project60.banking'}Add{/ts}</span>
</a>

<div id="banking_account_form" hidden="1">
  <h3>{ts domain='org.project60.banking'}Edit Bank Account{/ts}</h3>
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
    <div class="content">{$form.reference.html}&nbsp;<img id="reference_status_img" src="{$config->resourceBase}i/spacer.gif" height="10"/></div>
    <div class="clear"></div>
  </div>
  <div id="banking_account_data">
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
  </div>
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>



<script type="text/javascript">
var bank_accounts   = {$bank_accounts_json};
var reference_types = {$reference_types_json};
var validate_ref    = {$reference_validation};
var normalise_ref   = {$reference_normalisation};
var busy_icon_url   = "{$config->resourceBase}i/loading.gif";
var error_icon_url  = "{$config->resourceBase}i/Error.gif";
var good_icon_url   = "{$config->resourceBase}i/check.gif";
var no_icon_url     = "{$config->resourceBase}i/spacer.gif";
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
  cj("#banking_account_data").show();
  cj("input[name='ba_id']").val('');
  cj("input[name='reference_id']").val('');
  cj('html, body').animate({scrollTop: cj("#banking_account_form").offset().top});
}

/** JS function for editing a bank account */
function banking_editaccount(ba_id) {
  cj("#banking_account_addbtn").hide();
  cj("#banking_account_form").show();
  cj("#banking_account_data").show();
  cj("#bank_name").val(bank_accounts[ba_id].data_parsed.name);
  cj("#bic").val(bank_accounts[ba_id].data_parsed.BIC);
  cj("#country").val(bank_accounts[ba_id].data_parsed.country);
  cj("input[name='ba_id']").val(ba_id);

  cj("input[name='reference_id']").val(bank_accounts[ba_id].references[0].id);
  cj("#reference").val(bank_accounts[ba_id].references[0].reference);
  var reference_type = bank_accounts[ba_id].references[0].reference_type;
  cj("#reference_type").val(bank_accounts[ba_id].references[0].reference_type_id);
  cj('html, body').animate({scrollTop: cj("#banking_account_form").offset().top});
}

/** JS function for adding a bank account reference */
function banking_addreference(ba_id) {
  cj("#banking_account_addbtn").hide();
  cj("#banking_account_form").show();
  cj("#banking_account_data").hide();
  cj("#bank_name").val(bank_accounts[ba_id].data_parsed.name);
  cj("#bic").val(bank_accounts[ba_id].data_parsed.BIC);
  cj("#country").val(bank_accounts[ba_id].data_parsed.country);
  cj("input[name='ba_id']").val(ba_id);
  cj("input[name='reference_id']").val('');
  cj("#reference").val('');
  cj('html, body').animate({scrollTop: cj("#banking_account_form").offset().top});
}

/** JS function for deleting a bank account */
function banking_deleteaccount(ba_id) {
  CRM.confirm(function() {
    CRM.api('BankingAccount', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': ba_id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          CRM.alert("{/literal}{ts domain='org.project60.banking'}The bank account has been deleted{/ts}", "{ts domain='org.project60.banking'}Success{/ts}{literal}", "success");
          var contentId = cj('#tab_bank_accounts').attr('aria-controls');
          cj('#' + contentId).load(CRM.url('civicrm/banking/accounts_tab', {'reset': 1, 'snippet': 1, 'force': 1, 'cid':{/literal}{$contact_id}{literal}}));
        }else{
          CRM.alert("{/literal}" + data['error_message'], "{ts domain='org.project60.banking'}Error{/ts}{literal}", "error");
        }
      }
    });
  },{
    message: {/literal}"{ts domain='org.project60.banking'}Are you sure you want to delete this bank account?{/ts}"{literal}
  });
}

/** JS function for deleting a bank account */
function banking_deletereference(ba_id, ref_id) {
  if (bank_accounts[ba_id].references.length < 2) {
    CRM.alert("{/literal}{ts domain='org.project60.banking'}A bank account has to have at least one reference.{/ts}", "{ts domain='org.project60.banking'}Failed{/ts}{literal}", "warning");
    return;
  }

  CRM.confirm(function() {
    CRM.api('BankingAccountReference', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': ref_id},
    {success: function(data) {
        if (data['is_error'] == 0) {
          CRM.alert("{/literal}{ts domain='org.project60.banking'}The bank account reference has been deleted{/ts}", "{ts domain='org.project60.banking'}Success{/ts}{literal}", "success");
          var contentId = cj('#tab_bank_accounts').attr('aria-controls');
          cj('#' + contentId).load(CRM.url('civicrm/banking/accounts_tab', {'reset': 1, 'snippet': 1, 'force': 1, 'cid':{/literal}{$contact_id}{literal}}));
        }else{
          CRM.alert("{/literal}" + data['error_message'], "{ts domain='org.project60.banking'}Error{/ts}{literal}", "error");
        }
      }
    }
  );
  },
  {
    message: {/literal}"{ts domain='org.project60.banking'}Are you sure you want to delete this bank account reference?{/ts}"{literal}
  });
}

// Verify REFERENCE
cj("#reference").change(function() {
  // ...only if enabled
  if (!validate_ref && !normalise_ref) {
    {/literal}{if $bic_extension_installed}
    {* if the BIC extension is installed, still look up bank information based on IBAN *}
    banking_iban_lookup();
    {/if}{literal}
    return;
  }

  // ...only if long enough
  var reference = cj(this).val();
  if (reference.length < 3) return; // reference not long enough

  // get reference type
  var reference_type_id = cj("#reference_type").val();
  var reference_type    = reference_types[reference_type_id];

  // call API to check/normalise reference
  cj(this).parent().append('&nbsp;<img id="reference_checking" height="12" src="' + busy_icon_url + '"/>');
  cj("#reference_status_img").attr('src', no_icon_url);
  CRM.api('BankingAccountReference', 'check', {'q': 'civicrm/ajax/rest', 'reference': reference, 'reference_type_name': reference_type.name},
    {success: function(data) {
      cj("#reference_checking").remove();
      cj("#reference_status_img").attr('src', no_icon_url);
      var result = data.values;
      if (validate_ref) {
        if (result.checked && !result.is_valid) {
          // this is not a valid!
          cj("#reference_status_img").attr('src', error_icon_url);
        }
        if (result.is_valid) {
          cj("#reference_status_img").attr('src', good_icon_url);          
        }        
      }
      if (normalise_ref && result.normalised) {
        cj("#reference").val(result.reference);
      }
      {/literal}{if $bic_extension_installed}
      {* if the BIC extension is installed, look up bank information based on IBAN *}
      banking_iban_lookup();
      {/if}{literal}
    }, error: function(result, settings) {
      // we suppress the message box here
      // and log the error via console
      cj("#reference_checking").remove();
      return false;
    }});  
});

{/literal}
</script>

{if $bic_extension_installed}
{literal}
<script type="text/javascript">

// Look up bank name by BIC
cj("#bic").change(function() {
  var bic = cj(this).val();
  if (bic.length < 5) return;
  cj(this).parent().append('&nbsp;<img id="bic_busy" height="12" src="' + busy_icon_url + '"/>');
  CRM.api('Bic', 'get', {'q': 'civicrm/ajax/rest', 'bic': bic},
    {success: function(data) {
      if (data.count==1) {
        var ba = data.values[data.id];
        cj("#bank_name").val(ba.title);
        cj("#country").val(ba.country);
      } else if (data.count==0) {
        // no data found
      } else {
        // TODO: common substring?
        // console.log("found multiple");
      }
      cj("#bic_busy").remove();
    }, error: function(result, settings) {
      // we suppress the message box here
      // and log the error via console
      cj("#bic_busy").remove();
      return false;
    }});  
});

// Look up IBAN
function banking_iban_lookup() {
  // ...only if long enough
  var reference = cj("#reference").val();
  if (reference.length < 6) return; // IBAN not long enough

  // ...only if IBAN
  var reference_type_id = cj("#reference_type").val();
  var reference_type    = reference_types[reference_type_id];
  if (reference_type.name != 'IBAN') return;

  cj(this).parent().append('&nbsp;<img id="iban_busy" height="12" src="' + busy_icon_url + '"/>');
  CRM.api('Bic', 'findbyiban', {'q': 'civicrm/ajax/rest', 'iban': reference},
    {success: function(data) {
      if (data.is_error==0) {
        cj("#bic").val(data.bic);
        cj("#bank_name").val(data.title);
        cj("#country").val(data.country);
      } else {
        // not found.
      }
      cj("#iban_busy").remove();
    }, error: function(result, settings) {
      // we suppress the message box here
      // and log the error via console
      cj("#iban_busy").remove();
      return false;
    }});  
}
</script>
{/literal}
{/if}