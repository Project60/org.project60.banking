{if $results}
	<div>
	<table id="contact-activity-selector-dashlet">
	<thead>
		<tr>
			<th><div>{ts}Bank Account{/ts}</div></th>
			<th><div>{ts}Bank{/ts}</div></th>
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
	    			{else}
	    			{$reference.reference_type}:&nbsp;{$reference.reference}
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
	  </tr>
	  {/foreach}
	</tbody>
	</table>
	</div>
{else}
<h3>{ts}This contact has no known accounts associated with him/her.{/ts}</h3>
{/if}
