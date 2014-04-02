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

	{if $account_data.before.sum == 0}
	<td class="week_none"><i>{ts}no records{/ts}</i></td>
	{elseif $account_data.before.sum == $account_data.before.done}
	<td class="week_complete">{$account_data.before.done} / {$account_data.before.sum}</td>
	{else}
	<td class="week_incomplete">{$account_data.before.done} / {$account_data.before.sum}</td>
	{/if}

{foreach from=$weeks item=week}
	{if $account_data.$week.sum == 0}
	<td class="week_none"><i>{ts}no records{/ts}</i></td>
	{elseif $account_data.$week.sum == $account_data.$week.done}
	<td class="week_complete">{$account_data.$week.done} / {$account_data.$week.sum}</td>
	{else}
	<td class="week_incomplete">{$account_data.$week.done} / {$account_data.$week.sum}</td>
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

