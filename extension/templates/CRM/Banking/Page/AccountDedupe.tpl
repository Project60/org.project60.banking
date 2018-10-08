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

<div>{ts domain='org.project60.banking'}This page finds and lists duplicate or conflicting bank account information.{/ts}</div>

<br/>
<h3>{ts domain='org.project60.banking'}Duplicate References{/ts} ({$duplicate_references_count})</h3>
{if $duplicate_references_count}
<div>{ts domain='org.project60.banking'}These are identical account references (e.g. account numbers) that point to the same bank account. The duplicates can be removed safely.{/ts}</div>
<table>
	<thead>
		<th></th>
		<th></th>
		<th>{ts domain='org.project60.banking'}Bank Account Owners{/ts}</th>
		<th>{ts domain='org.project60.banking'}Count{/ts}</th>
		<th>{ts domain='org.project60.banking'}Account Reference{/ts}</th>
	</thead>
	<tbody>
{foreach from=$duplicate_references item=duplicate}
		<tr class="{cycle values="odd,even"}">
			<td>[{counter name=dup_ref}]</td>
			<td>
				{assign var=duplicate_reference value=$duplicate.reference_id}
				<a class="button" href="{crmURL p="civicrm/banking/dedupe" q="fixref=$duplicate_reference"}">
  				<span align="right"><div class="icon delete-icon ui-icon-trash"></div>{ts domain='org.project60.banking'}fix{/ts}</span>
  			</a>
			</td>
			<td>
				{assign var=contact_id value=$duplicate.contact.id}
				<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">
					{$duplicate.contact.display_name} [{$duplicate.contact.id}]
				</a>
			</td>
			<td>{$duplicate.dupe_count}</td>
			<td>
				<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id&selectedChild=bank_accounts"}" title="{$duplicate.reference_type.label}">
					{$duplicate.reference_type.name}: {$duplicate.reference}
				</a>
			</td>
		</tr>
{/foreach}
		<tr><td></td></tr>
		<tr>
			<td>
				<a class="button" href="{crmURL p="civicrm/banking/dedupe" q="fixref=all"}">
					<span align="right"><div class="icon delete-icon ui-icon-trash"></div>{ts domain='org.project60.banking'}fix all{/ts}</span>
				</a>
			</td>
			<td></td>
			<td></td>
			<td></td>
	</tbody>
</table>

{else}
<div>{ts domain='org.project60.banking'}No duplicate references found.{/ts}</div>
{/if}



<br/><br/>
<h3>{ts domain='org.project60.banking'}Duplicate Bank Accounts{/ts} ({$duplicate_accounts_count})</h3>
{if $duplicate_accounts_count}
<div>{ts domain='org.project60.banking'}These are duplicate bank accounts listed for the <i>same</i> contact. In most cases, the bank accounts can be merged automatically.{/ts}</div>
<table>
	<thead>
		<th></th>
		<th></th>
		<th>{ts domain='org.project60.banking'}Bank Account Owners{/ts}</th>
		<th>{ts domain='org.project60.banking'}Count{/ts}</th>
		<th>{ts domain='org.project60.banking'}Account Reference{/ts}</th>
	</thead>
	<tbody>
{foreach from=$duplicate_accounts item=duplicate}
		<tr class="{cycle values="odd,even"}">
			<td>[{counter name=dup_acc}]</td>
			<td>
				{assign var=duplicate_reference value=$duplicate.reference_id}
				<a class="button" href="{crmURL p="civicrm/banking/dedupe" q="fixdupe=$duplicate_reference"}">
  				<span align="right"><div class="icon delete-icon ui-icon-trash"></div>{ts domain='org.project60.banking'}fix{/ts}</span>
  			</a>
			</td>
			<td>
				{assign var=contact_id value=$duplicate.contact.id}
				<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">
					{$duplicate.contact.display_name} [{$duplicate.contact.id}]
				</a>
			</td>
			<td>{$duplicate.dupe_count}</td>
			<td>
				<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id&selectedChild=bank_accounts"}" title="{$duplicate.reference_type.label}">
					{$duplicate.reference_type.name}: {$duplicate.reference}
				</a>
			</td>
		</tr>
{/foreach}
		<tr><td></td></tr>
		<tr>
			<td>
				<a class="button" href="{crmURL p="civicrm/banking/dedupe" q="fixdupe=all"}">
					<span align="right"><div class="icon delete-icon ui-icon-trash"></div>{ts domain='org.project60.banking'}fix all{/ts}</span>
				</a>
			</td>
			<td></td>
			<td></td>
			<td></td>
	</tbody>
</table>

{else}
<div>{ts domain='org.project60.banking'}No duplicate accounts found.{/ts}</div>
{/if}




<br/><br/>
<h3>{ts domain='org.project60.banking'}Bank Account Ownership Conflicts{/ts} ({$account_conflicts_count})</h3>
{if $account_conflicts_count}
<div>{ts domain='org.project60.banking'}These are duplicate bank accounts listed for <i>different</i> contacts. Those that are not intended to be "shared" need to be resolved manually - sorry.{/ts}</div>
<table>
	<thead>
		<th></th>
		<th>{ts domain='org.project60.banking'}Bank Account Owners{/ts}</th>
		<th>{ts domain='org.project60.banking'}Account Reference{/ts}</th>
		<th></th>
	</thead>
	<tbody>
{foreach from=$account_conflicts item=duplicate}
  {if not $duplicate.exception}
		<tr class="{cycle values="odd,even"}">
			<td>[{counter name=dup_cnt}]</td>
			<td>
				<ul>
{foreach from=$duplicate.contacts item=contact}
				{assign var=contact_id value=$contact.id}
				<li>
					<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id&selectedChild=bank_accounts"}">
						{$contact.display_name} [{$contact.id}]
					</a>
				</li>
{/foreach}
			</ul></td>
			<td>
				<a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id&selectedChild=bank_accounts"}" title="{$duplicate.reference_type.label}">
					{$duplicate.reference_type.name}: {$duplicate.reference}
				</a>
			</td>
			<td>
				{if $duplicate.merge_link}
				<a class="button" href="{$duplicate.merge_link}">
  				<span align="right"><div class="icon ui-icon-check"></div>{ts domain='org.project60.banking'}merge{/ts}</span>
  			</a>
  			{/if}
  			<br/>
				{if $duplicate.exclude_link}
				<a class="button" href="{$duplicate.exclude_link}">
  				<span align="right"><div class="icon ui-icon-close"></div>{ts domain='org.project60.banking'}exclude{/ts}</span>
  			</a>
  			<br/>
  			{/if}
			</td>
		</tr>
	{/if}
{/foreach}
	</tbody>
</table>

{else}
<div>{ts domain='org.project60.banking'}No conflicting bank accounts found.{/ts}</div>
{/if}
