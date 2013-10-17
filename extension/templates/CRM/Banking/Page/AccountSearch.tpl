
<form action="{$url_action}" method="post" name="DataSource" id="DataSource" enctype="multipart/form-data" >

<div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
    <h3>Search Criteria</h3>
    <table class="form-layout">
    	<tbody>
	        <tr class="crm-import-datasource-form-block-dataSource">
				<td class="label">
					<label for="dataSource">Bank account number<br/>(or partials)</label>
				</td>
				<td>
					<input id="reference_partial" class="form-text" type="text" name="reference_partial" value="{$smarty.post.reference_partial}"></input>
				</td>
				<td>
					<input type="checkbox" class="form-checkbox" value="off" name="full_search" id="full_search" 
					{if $smarty.post.full_search}checked{/if}>
					Also search additional account information</input>
				</td>
			</tr>
			<tr>
				<td>
				    <span class="crm-button">
	      				<input type="submit" value="Search" class="validate form-submit default">
	    			</span>
	    		</td>
    		</tr>
  		</tbody>
	</table>
</div>

</form>

{* Results *}

{if $smarty.post.reference_partial}
	{* i.e. there is an ongoing query *}
	{if $results}
		<h3>{$results|@count} accounts match your search</h3>
		<div>
		<table id="contact-activity-selector-dashlet">
		<thead>
			<tr>
				<th colspan="1" rowspan="1" class="crm-banking-payment_target_owner ui-state-default">
					<div class="DataTables_sort_wrapper">Contact<span class="DataTables_sort_icon css_right ui-icon ui-icon-carat-2-n-s"></span></div>
				</th>
				<th colspan="1" rowspan="1" class="crm-banking-payment_target_owner ui-state-default">
					<div class="DataTables_sort_wrapper">Account #<span class="DataTables_sort_icon css_right ui-icon ui-icon-carat-2-n-s"></span></div>
				</th>
				<th colspan="1" rowspan="1" class="crm-banking-payment_date ui-state-default">
					<div class="DataTables_sort_wrapper">Account Information<span class="DataTables_sort_icon css_right ui-icon ui-icon-carat-2-n-s"></span></div>
				</th>
			</tr>
		</thead>
		<tbody>
		  {foreach from=$results item=match}
		  <tr class="{cycle values="odd,even"}">
		    <td><a href="{$match.contact_link}"><div class="icon crm-icon {$match.contact_type}-icon"></div>{$match.display_name}</a></td>
		    <td>{$match.reference} ({$match.reference_type})</td>
		    <td>
		    	<table border="0">
					{foreach from=$match.data_parsed item=value key=key}
		    		<tr>
		    			<td style="width: 130px;"><b>{$key}</b></td>
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
	<h3>Sorry, no accounts match your search!</h3>

	{/if}
{/if}
