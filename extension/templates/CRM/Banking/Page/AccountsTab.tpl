{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

{if $results}
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
	  {foreach from=$results item=account}
	  <tr class="{cycle values="odd,even"}">	  	
	    <td>
	    	<table style="border: 0;">
				{foreach from=$account.references item=reference}
	    		<tr><td>
	    			{if $reference.reference_type eq 'NBAN_DE'}
	    			{assign var=german value="/"|explode:$reference.reference} 
	    			({ts}German{/ts})&nbsp;&nbsp;&nbsp;BLZ:&nbsp;{$german.0}&nbsp;&nbsp;&nbsp;Kontonummer:&nbsp;{$german.1}
	    			{elseif $reference.reference_type eq 'ENTITY'}
	    			{* We hide entity references for the moment *}
	    			{else}
	    			<span title="{$reference.reference_type_label}">[{$reference.reference_type}]&nbsp;{$reference.reference}</span>
	    			{/if}
	    		</td></tr>
	    		{/foreach}
	    	</table>
	    </td>
	    <td>
	    	<table style="border: 0;">
				{foreach from=$account.data_parsed item=value key=key}
	    		<tr>
	    			<td style="width: 130px;"><b>{ts}{$key}{/ts}</b></td>
	    			<td>{$value}</td>
	    		</tr>
	    		{/foreach}
	    	</table>
	    </td>
	    <td style="vertical-align: middle;">
	    	<a title="{ts}Delete{/ts}" class="delete button" onClick="deleteAccount({$account.id});">
              <span><div class="icon delete-icon ui-icon-trash"></div>{ts}Delete{/ts}</span>
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

<a id="create_account_button" title="{ts}Add{/ts}" class="add button" onClick="showCreateAccount();">
  <span><div class="icon add-icon ui-icon-circle-plus"></div>{ts}Add{/ts}</span>
</a>

<div>
  <div class="crm-section">
    <div class="label">{$form.country.label}</div>
    <div class="content">{$form.country.html}</div>
    <div class="clear"></div>
  </div>
</div>

<!--form id="create_account_form" hidden="1">
	<table>
		<tr>
			<td>{ts}Account Type{/ts}:</td>
			<td>
				<select name="reference_type_id" id="reference_type_id">
					{foreach from=$reference_types item=reference_type}
					<option value={$reference_type.id}>{$reference_type.label}</option>
					{/foreach}
				</select>
				<br/>
				<span id="reference_format_help"></span>
			</td>
		</tr>
		<tr>
			<td>Account Number</td>
			<td><input name="reference" type="text" size="40" value=""/></td>
		</tr>
		<tr>
			<td>{ts}Bank Name{/ts}:</td>
			<td><input name="bank_name" type="text" size="40" value=""/></td>
		</tr>
		<tr>
			<td>BIC:</td>
			<td><input name="bic" type="text" size="14" value=""/></td>
		</tr>
		<tr>
			<td>{ts}Country{/ts}:</td>
			<td>
				<select name="country" id="country">
					{foreach from=$countries key=iso item=name}
					<option value={$iso}>{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
	</table>
	<input type="submit" value="{ts}Add{/ts}" />
</form-->

<script type="text/javascript">
var accounts_reload_url = "{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id&selectedChild=bank_accounts"}";
var contact_id = {$contact_id};
var iban_type_id = "{$iban_type_id}";
var error_message_title = "{ts}Error{/ts}";
var bad_bic_message = "{ts}BIC is too short!{/ts}";
var bad_iban_message = "{ts}IBAN is wrong!{/ts}";

{literal}
function showCreateAccount(account_id) {
	cj('#create_account_form').show();
	cj('#create_account_button').hide();
}

function createAccount(e) {
	e.preventDefault();
	var iban = cj("[name='iban']").val();
	var bic = cj("[name='bic']").val();
	var bank_name = cj("[name='bank_name']").val();
	var data_parsed = {'bank':bank_name, 'BIC': bic, 'country': iban.substring(0,2)}

	// do some validation
	if (iban.length < 14) {
		// TODO: do proper validation
		alert(bad_iban_message, error_message_title);
		return;
	}
	if (bic.length < 8) {
		alert(bad_bic_message, error_message_title);
		return;
	}

	CRM.api('BankingAccount', 'create', {	'q': 'civicrm/ajax/rest',
											'version': 3, 
											'sequential': 1,
											'data_parsed': JSON.stringify(data_parsed),
											'contact_id': contact_id },
  		{	success: function(data) {
  			// account succesfully created, add reference
			CRM.api('BankingAccountReference', 'create', {	'q': 'civicrm/ajax/rest', 
															'sequential': 1,
															'version': 3, 
															'reference': iban,
															'ba_id': data.id,
															'reference_type_id': iban_type_id
															},
			  { success: function(data) {
			      // success!
			      accounts_reload();
			    },
		        error: function(err, msg) {
			      // error!
			      alert(msg, error_message_title);
			    }
			  }
			);
    	},	error: function(err, msg) {
    		alert(msg, error_message_title);
    	}
  	});
}
cj('#create_account_form').submit(createAccount);

function deleteAccount(account_id) {
	console.log(account_id);
	CRM.api('BankingAccount', 'delete', {'q': 'civicrm/ajax/rest', 'id': account_id, 'version': 3 },
	  {success: function(data) {
	      accounts_reload();
	    }
	  }
	);
}

function accounts_reload() {
	accounts_reload_url = cj("<div/>").html(accounts_reload_url).text();
	location.href = accounts_reload_url;
}

</script>
{/literal}
