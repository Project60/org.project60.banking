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

{* TODO: move to CSS file? *}
<style type="text/css">
{literal}
tr.banking-plugin-disabled {
	color: lightgray;
}
{/literal}
</style>

{* DELETION CONFIRMATION PAGE *}
{if $delete}
	<h3>{ts 1=$delete.name}Delete Plugin "%1"{/ts}</h3>
	<div>
		<p>
			{ts 1=$delete.name 2=$delete.id}You are about to delete plugin "%1" [%2]. You should consider simply disabling it, since all banking transactions that have been processed using this plugin would have a missing link if go through with the deletion. Do you want to delete it anyway?{/ts}
		</p>
		{assign var=plugin_id value=$delete.id}
		<a id="crm-create-new-link" class="button" href="{crmURL p="civicrm/banking/manager" q="reset=1&confirmed=1&delete=$plugin_id"}">
			<span><div class="icon ui-icon-trash css_left"></div>Delete</span>
		</a>
		<a id="crm-create-new-link" class="button" href="{crmURL p="civicrm/banking/manager"}">
			<span>Back</span>
		</a>
 </div>
{else}

{* NORMAL PAGE *}
<h3>{ts}Import Plugins{/ts}</h3>
<div id="help">
	{ts}Import plugins are used to transport transaction data obtained from banks and accounting software into CiviBanking. They source the information from files as well as from data feeds from external systems. Once imported, the payments will be processed by the matcher plugins.{/ts}
	<a href="{crmURL p="civicrm/banking/configure" q="reset=1&type=$type_import"}">{ts}Add a new one.{/ts}</a>
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Plugin{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Type{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Enabled?{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Selection Order{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1"></th>
		</tr>
	</thead>
	<tbody>
	{foreach from=$importers item=importer}
		<tr class="{cycle values="odd-row,even-row"} {if not $importer.enabled}banking-plugin-disabled{/if}">
			{assign var=plugin_id value=$importer.id}
			<td>[{$importer.id}] {$importer.name}</td>
			<td>{$importer.description}</td>
			<td>{$importer.class}</td>
			<td>{if $importer.enabled}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td>
			<td>
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="top=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/first.gif" title="Move to top" alt="Move to top" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="up=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/up.gif" title="Move up one row" alt="Move up one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="down=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/down.gif" title="Move down one row" alt="Move down one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="bottom=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/last.gif" title="Move to bottom" alt="Move to bottom" class="order-icon"></a>
			</td>
			<td>
				<span class="btn-slide crm-hover-button">{ts}Actions{/ts}
					<ul class="panel">
						<li>
							{if $importer.enabled}
								<a href="{crmURL p='civicrm/banking/manager' q="disable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Disable{/ts}">{ts}Disable{/ts}</a>
							{else}
								<a href="{crmURL p='civicrm/banking/manager' q="enable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Enable{/ts}">{ts}Enable{/ts}</a>
							{/if}
							<a href="{crmURL p='civicrm/banking/configure' q="reset=1&pid=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Configure{/ts}">{ts}Configure{/ts}</a>
							<a href="{crmURL p='civicrm/banking/manager' q="delete=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Delete{/ts}">{ts}Delete{/ts}</a>
						</li>
					</ul>
				</span>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>

<br/><br/>
<h3>{ts}Analyser / Matcher Plugins{/ts}</h3>
<div id="help">
	{ts}Matcher Plugins are used to match the transactions with the expected financial transactions, such as contributions, membership fees, etc. If no perfect match can be found, they will generate proposals for the user to review and confirm.{/ts}
	<a href="{crmURL p="civicrm/banking/configure" q="reset=1&type=$type_match"}">{ts}Add a new one.{/ts}</a>
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Plugin{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Type{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Enabled?{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Execution Order{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1"></th>
		</tr>
	</thead>
	<tbody>
	{foreach from=$matchers item=matcher}
		<tr class="{cycle values="odd-row,even-row"} {if not $matcher.enabled}banking-plugin-disabled{/if}">
			{assign var=plugin_id value=$matcher.id}
			<td>[{$matcher.id}] {$matcher.name}</td>
			<td>{$matcher.description}</td>
			<td>{$matcher.class}</td>
			<td>{if $matcher.enabled}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td>
			<td>
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="top=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/first.gif" title="Move to top" alt="Move to top" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="up=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/up.gif" title="Move up one row" alt="Move up one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="down=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/down.gif" title="Move down one row" alt="Move down one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="bottom=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/last.gif" title="Move to bottom" alt="Move to bottom" class="order-icon"></a>
			</td>
			<td>
				<span class="btn-slide crm-hover-button">{ts}Actions{/ts}
					<ul class="panel">
						<li>
							{if $matcher.enabled}
								<a href="{crmURL p='civicrm/banking/manager' q="disable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Disable{/ts}">{ts}Disable{/ts}</a>
							{else}
								<a href="{crmURL p='civicrm/banking/manager' q="enable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Enable{/ts}">{ts}Enable{/ts}</a>
							{/if}
							<a href="{crmURL p='civicrm/banking/configure' q="reset=1&pid=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Configure{/ts}">{ts}Configure{/ts}</a>
							<a href="{crmURL p='civicrm/banking/manager' q="delete=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Delete{/ts}">{ts}Delete{/ts}</a>
						</li>
					</ul>
				</span>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>

<br/><br/>
<h3>{ts}Postprocessors{/ts}</h3>
<div id="help">
	{ts}Postprocessors are plugins that perform certain extra tasks once the correct contact, contribution, or other entity has been identified.{/ts}
	<a href="{crmURL p="civicrm/banking/configure" q="reset=1&type=$type_postprocess"}">{ts}Add a new one.{/ts}</a>
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Plugin{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Type{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Enabled?{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Execution Order{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1"></th>
		</tr>
	</thead>
	<tbody>
	{foreach from=$postprocessors item=postprocessor}
		<tr class="{cycle values="odd-row,even-row"} {if not $postprocessor.enabled}banking-plugin-disabled{/if}">
			{assign var=plugin_id value=$postprocessor.id}
			<td>[{$postprocessor.id}] {$postprocessor.name}</td>
			<td>{$postprocessor.description}</td>
			<td>{$postprocessor.class}</td>
			<td>{if $postprocessor.enabled}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td>
			<td>
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="top=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/first.gif" title="Move to top" alt="Move to top" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="up=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/up.gif" title="Move up one row" alt="Move up one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="down=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/down.gif" title="Move down one row" alt="Move down one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="bottom=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/last.gif" title="Move to bottom" alt="Move to bottom" class="order-icon"></a>
			</td>
			<td>
				<span class="btn-slide crm-hover-button">{ts}Actions{/ts}
					<ul class="panel">
						<li>
							{if $postprocessor.enabled}
								<a href="{crmURL p='civicrm/banking/manager' q="disable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Disable{/ts}">{ts}Disable{/ts}</a>
							{else}
								<a href="{crmURL p='civicrm/banking/manager' q="enable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Enable{/ts}">{ts}Enable{/ts}</a>
							{/if}
							<a href="{crmURL p='civicrm/banking/configure' q="reset=1&pid=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Configure{/ts}">{ts}Configure{/ts}</a>
							<a href="{crmURL p='civicrm/banking/manager' q="delete=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Delete{/ts}">{ts}Delete{/ts}</a>
						</li>
					</ul>
				</span>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>

<br/><br/>
<h3>{ts}Exporter Plugins{/ts}</h3>
<div id="help">
	{ts}Export plugins are used to save the processed transaction data along with their matching financial transactions into files or accounting systems.{/ts}
	<a href="{crmURL p="civicrm/banking/configure" q="reset=1&type=$type_export"}">{ts}Add a new one.{/ts}</a>
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Plugin{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Type{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Enabled?{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Selection Order{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1"></th>
		</tr>
	</thead>
	<tbody>
	{foreach from=$exporters item=exporter}
		<tr class="{cycle values="odd-row,even-row"} {if not $exporter.enabled}banking-plugin-disabled{/if}">
			{assign var=plugin_id value=$exporter.id}
			<td>[{$exporter.id}] {$exporter.name}</td>
			<td>{$exporter.description}</td>
			<td>{$exporter.class}</td>
			<td>{if $exporter.enabled}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td>
			<td>
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="top=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/first.gif" title="Move to top" alt="Move to top" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="up=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/up.gif" title="Move up one row" alt="Move up one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="down=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/down.gif" title="Move down one row" alt="Move down one row" class="order-icon"></a>&nbsp;
        <a class="crm-weight-arrow" href="{crmURL p='civicrm/banking/manager' q="bottom=$plugin_id"}"><img src="{$config->resourceBase}i/arrow/last.gif" title="Move to bottom" alt="Move to bottom" class="order-icon"></a>
			</td>
			<td>
				<span class="btn-slide crm-hover-button">{ts}Actions{/ts}
					<ul class="panel">
						<li>
							{if $exporter.enabled}
								<a href="{crmURL p='civicrm/banking/manager' q="disable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Disable{/ts}">{ts}Disable{/ts}</a>
							{else}
								<a href="{crmURL p='civicrm/banking/manager' q="enable=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Enable{/ts}">{ts}Enable{/ts}</a>
							{/if}
							<a href="{crmURL p='civicrm/banking/configure' q="reset=1&pid=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Configure{/ts}">{ts}Configure{/ts}</a>
							<a href="{crmURL p='civicrm/banking/manager' q="delete=$plugin_id"}" class="action-item crm-hover-button delete-contact small-popup" title="{ts}Delete{/ts}">{ts}Delete{/ts}</a>
						</li>
					</ul>
				</span>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>
{/if}

<script type="text/javascript">
// reset the URL
window.history.replaceState("", "", "{$baseurl}");
</script>