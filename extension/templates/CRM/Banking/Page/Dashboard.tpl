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

<style>
{literal}
td.week_none {
	background-color: lightgrey;
	color: white;
	text-align: center;
}
td.week_complete {
	background-color: lightgreen;
	text-align: center;
}
td.week_incomplete {
	background-color: orange;
	text-align: center;
}
{/literal}
</style>


<br/>
<h2>{ts}Weekly overview{/ts}</h2>
<table>
<thead>
	<td><b>{ts}Account{/ts}</b></td>
	<td align="center">{ts}in the past{/ts}</td>
{foreach from=$weeks item=week}
	<td align="center">{ts}week{/ts} {$week|substr:4:2}</td>
{/foreach}
</thead>

<tbody>
{foreach from=$account_week_data item=account_data key=account_id}
<tr>
	<td>{$account_names.$account_id}</td>
	{assign var=done value=$account_data.before.done}
	{if not $done}{assign var=done value=0}{/if}
	{assign var=sum value=$account_data.before.sum}
	{if not $sum}{assign var=sum value=0}{/if}

	{if $sum == 0}
	<td class="week_none" title="{ts}There are no records for this time span.{/ts}"><i>{ts}no records{/ts}</i></td>
	{elseif $sum == $done}
	<td class="week_complete" title="{$done} / {$sum}">100&nbsp;%</td>
	{else}
	<td class="week_incomplete" title="{$done} / {$sum}">{math equation="floor(done/count*100.0)" done=$done count=$sum}&nbsp;%</td>
	{/if}

{foreach from=$weeks item=week}
	{assign var=done value=$account_data.$week.done}
	{if not $done}{assign var=done value=0}{/if}
	{assign var=sum value=$account_data.$week.sum}
	{if not $sum}{assign var=sum value=0}{/if}

	{if $sum == 0}
	<td class="week_none" title="{ts}There are no records for this time span.{/ts}"><i>{ts}no records{/ts}</i></td>
	{elseif $sum == $done}
	<td class="week_complete" title="{$done} / {$sum}">100&nbsp;%</td>
	{else}
	<td class="week_incomplete" title="{$done} / {$sum}">{math equation="floor(done/count*100.0)" done=$done count=$sum}&nbsp;%</td>
	{/if}
{/foreach}
<tr>
{/foreach}
</tbody>
</table>


<br/>
<h2>{ts}Statistics{/ts}</h2>
<table>
	<thead>
		{foreach from=$statistics item=data}
		<td><b>{$data.title}</b><td>
		{/foreach}
	</thead>
	<tbody>
		{foreach from=$statistics item=data}
		<td>
		<table><tr>
			<td><b>{ts}<domain="temporal">From</domain>{/ts}</b></td>
			<td><b>{ts}{$data.from|substr:0:10}{/ts}</b></td>
		</tr><tr>
			<td><b>{ts}<domain="temporal">To</domain>{/ts}</td>
			<td><b>{ts}{$data.to|substr:0:10}{/ts}</td>
		</tr><tr>
		{foreach from=$data.stats item=count key=label}
		<td>{ts}{$label}{/ts}</td>
		<td>{$count}</td>
		</tr><tr>
		{/foreach}
		</tr><tr>
			<td><b>{ts}Total{/ts}</b></td>
			<td><b>{ts}{$data.count}{/ts}</b></td>
		</tr><tr>
		</tr></table>
		<td>
		{/foreach}
	</tbody>
</table>

