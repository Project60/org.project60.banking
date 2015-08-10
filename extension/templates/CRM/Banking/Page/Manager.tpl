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

<h2><font color="red">{ts}Sorry, the configuration interface is not yet functional!{/ts}</font></h2>
<br/>
<h3>Import Plugins</h3>
<div id="help">
	Import plugins are used to transport transaction data obtained from banks and accounting software into CiviBanking. They source the information from files as well as from data feeds from external systems. Once imported, the payments will be processed by the matcher plugins.
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting" rowspan="1" colspan="1">Plugin</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Interface{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Profiles{/ts}</th>
		</tr>
	</thead>
	<!--tbody>
		<tr class="odd-row">
			<td rowspan="3">CSV Importer</td>
			<td rowspan="3">Imports CSV based data</td>
			<td rowspan="3">UI only</td>
			<td><a class="button" href="#"><span><div class="icon add-icon ui-icon-circle-plus"></div>Add Profile</span></a></td>
		</tr>
		<tr class="odd-row">
			<td><b>Default</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">Edit</a></td>
		</tr>
		<tr class="odd-row">
			<td><b>GLS Online Banking</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">Edit</a>&nbsp;<a href='#' class="crm-actions-view">Delete</a></td>
		</tr>
		<tr class="even-row">
			<td rowspan="2">SEPA XML</td>
			<td rowspan="2">PAIN data processor</td>
			<td rowspan="2">UI and API</td>
			<td><a class="button" href="#"><span><div class="icon add-icon ui-icon-circle-plus"></div>Add Profile</span></a></td>
		</tr>
		<tr class="even-row">
			<td><b>Default</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">Edit</a></td>
		</tr>
	</tbody-->
</table>

<br/><br/>
<h3>Matcher Plugins</h3>
<div id="help">
	Matcher Plugins are used to match the transactions with the expected financial transactions, such as contributions, membership fees, etc. If no perfect match can be found, they will generate proposals for the user to review and confirm.
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting" rowspan="1" colspan="1">{ts}Matcher{/ts}</th>
			<th class="sorting" rowspan="1" colspan="1">{ts}Weight{/ts}</th>
			<th id="nosort" class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th id="order" class="sorting" rowspan="1" colspan="1">Order</th>
			<th class="sorting" rowspan="1" colspan="1">{ts}Review Required{/ts}</th>
			<th class="sorting" rowspan="1" colspan="1">{ts}Enabled?{/ts}</th>
			<th class="hiddenElement sorting_disabled" rowspan="1" colspan="1"></th>
		</tr>
	</thead>
	<!--tbody>
		<tr class="crm-admin-options crm-admin-options_73 odd-row" id="row_73">
			<td class="crm-admin-options-label ">SEPA Matcher</td>
			<td class="crm-admin-options-value ">5</td>
			<td class="crm-admin-options-description ">Processes confirmations of payments that have been issued by the SEPA payment processor.</td>
			<td class="nowrap crm-admin-options-order">
				<img class="order-icon" src="/drupal/sites/all/modules/civicrm/i/arrow/spacer.gif">
				<img class="order-icon" src="/drupal/sites/all/modules/civicrm/i/arrow/spacer.gif">
				<img class="order-icon" alt="Move down one row" title="Move down one row" src="/drupal/sites/all/modules/civicrm/i/arrow/down.gif">
				<img class="order-icon" alt="Move to bottom" title="Move to bottom" src="/drupal/sites/all/modules/civicrm/i/arrow/last.gif">
			</td>
			<td class="crm-admin-options-is_reserved "> No </td>
			<td id="row_73_status" class="crm-admin-options-is_active "> Yes </td>
		</tr>
		<tr class="crm-admin-options crm-admin-options_74 even-row" id="row_74">
			<td class="crm-admin-options-label ">Membership Matcher</td>
			<td class="crm-admin-options-value ">2</td>
			<td class="crm-admin-options-description ">Identifies membership payments in the payment stream.</td>
			<td class="nowrap crm-admin-options-order">
				<img class="order-icon" alt="Move to top" title="Move to top" src="/drupal/sites/all/modules/civicrm/i/arrow/first.gif">
				<img class="order-icon" alt="Move up one row" title="Move up one row" src="/drupal/sites/all/modules/civicrm/i/arrow/up.gif">
				<img class="order-icon" alt="Move down one row" title="Move down one row" src="/drupal/sites/all/modules/civicrm/i/arrow/down.gif">
				<img class="order-icon" alt="Move to bottom" title="Move to bottom" src="/drupal/sites/all/modules/civicrm/i/arrow/last.gif">
			</td>
			<td class="crm-admin-options-is_reserved "> Yes </td>
			<td id="row_74_status" class="crm-admin-options-is_active "> Yes </td>
		</tr>
		<tr class="crm-admin-options crm-admin-options_74 odd-row" id="row_74">
			<td class="crm-admin-options-label ">Pledge Matcher</td>
			<td class="crm-admin-options-value ">2</td>
			<td class="crm-admin-options-description ">Identifies pledge payments in the payment stream.</td>
			<td class="nowrap crm-admin-options-order">
				<img class="order-icon" alt="Move to top" title="Move to top" src="/drupal/sites/all/modules/civicrm/i/arrow/first.gif">
				<img class="order-icon" alt="Move up one row" title="Move up one row" src="/drupal/sites/all/modules/civicrm/i/arrow/up.gif">
				<img class="order-icon" alt="Move down one row" title="Move down one row" src="/drupal/sites/all/modules/civicrm/i/arrow/down.gif">
				<img class="order-icon" alt="Move to bottom" title="Move to bottom" src="/drupal/sites/all/modules/civicrm/i/arrow/last.gif">
			</td>
			<td class="crm-admin-options-is_reserved "> Yes </td>
			<td id="row_74_status" class="crm-admin-options-is_active "> Yes </td>
		</tr>
		<tr class="crm-admin-options crm-admin-options_74 even-row" id="row_74">
			<td class="crm-admin-options-label ">Reversing Entry Matcher</td>
			<td class="crm-admin-options-value ">2</td>
			<td class="crm-admin-options-description ">Identifies cancellation transactions.</td>
			<td class="nowrap crm-admin-options-order">
				<img class="order-icon" alt="Move to top" title="Move to top" src="/drupal/sites/all/modules/civicrm/i/arrow/first.gif">
				<img class="order-icon" alt="Move up one row" title="Move up one row" src="/drupal/sites/all/modules/civicrm/i/arrow/up.gif">
				<img class="order-icon" alt="Move down one row" title="Move down one row" src="/drupal/sites/all/modules/civicrm/i/arrow/down.gif">
				<img class="order-icon" alt="Move to bottom" title="Move to bottom" src="/drupal/sites/all/modules/civicrm/i/arrow/last.gif">
			</td>
			<td class="crm-admin-options-is_reserved "> No </td>
			<td id="row_74_status" class="crm-admin-options-is_active "> Yes </td>
		</tr>
		<tr class="crm-admin-options crm-admin-options_74 odd-row" id="row_74">
			<td class="crm-admin-options-label ">Contribution Matcher</td>
			<td class="crm-admin-options-value ">2</td>
			<td class="crm-admin-options-description ">Identifies contributions in the payment stream.</td>
			<td class="nowrap crm-admin-options-order">
				<img class="order-icon" alt="Move to top" title="Move to top" src="/drupal/sites/all/modules/civicrm/i/arrow/first.gif">
				<img class="order-icon" alt="Move up one row" title="Move up one row" src="/drupal/sites/all/modules/civicrm/i/arrow/up.gif">
				<img class="order-icon" src="/drupal/sites/all/modules/civicrm/i/arrow/spacer.gif">
				<img class="order-icon" src="/drupal/sites/all/modules/civicrm/i/arrow/spacer.gif">
			</td>
			<td class="crm-admin-options-is_reserved "> Yes </td>
			<td id="row_74_status" class="crm-admin-options-is_active "> Yes </td>
		</tr>
	</tbody-->
</table>

<br/><br/>
<h3>Export Plugins</h3>
<div id="help">
	Export plugins are used to save the processed transaction data along with their matching financial transactions into files or accounting systems.
</div>
<table class="display" id="option11">
	<thead>
		<tr>
			<th class="sorting" rowspan="1" colspan="1">Plugin</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Description{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Interface{/ts}</th>
			<th class="sorting_disabled" rowspan="1" colspan="1">{ts}Profiles{/ts}</th>
		</tr>
	</thead>
	<!--tbody>
		<tr class="odd-row">
			<td rowspan="3">CSV Exporter</td>
			<td rowspan="3">Imports CSV based data</td>
			<td rowspan="3">UI only</td>
			<td><a class="button" href="#"><span><div class="icon add-icon ui-icon-circle-plus"></div>Add Profile</span></a></td>
		</tr>
		<tr class="odd-row">
			<td><b>Default</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">{ts}Edit{/ts}</a></td>
		</tr>
		<tr class="odd-row">
			<td><b>GLS Online Banking</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">Edit</a>&nbsp;<a href='#' class="crm-actions-view">{ts}Delete{/ts}</a></td>
		</tr>
		<tr class="even-row">
			<td rowspan="2">SEPA XML</td>
			<td rowspan="2">PAIN data processor</td>
			<td rowspan="2">UI and API</td>
			<td><a class="button" href="#"><span><div class="icon add-icon ui-icon-circle-plus"></div>{ts}Add Profile{/ts}</span></a></td>
		</tr>
		<tr class="even-row">
			<td><b>Default</b>&nbsp;&nbsp;<a href='#' class="crm-actions-view">{ts}Edit{/ts}</a></td>
		</tr>
	</tbody-->
</table>


