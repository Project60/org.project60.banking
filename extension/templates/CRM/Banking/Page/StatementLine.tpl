{crmScope extensionKey='org.project60.banking'}

<form action="" method="get">
  <input type="hidden" name="s_id" value="{$statement_id}" />
  <div class="crm-block crm-form-block">
    <h3>{ts domain='org.project60.banking'}Filter lines{/ts}</h3>
    <table class="form-layout">
      <tbody>
      <tr>        
        <td>
        <strong>{ts domain='org.project60.banking'}Status{/ts}</strong>&nbsp;
        {foreach from=$statuses item=status key=status_id}
          {assign var="status_name" value=$status.name}
          <label><input type="checkbox" value="{$status_id}" name="status[]" {if (in_array($status_id, $selectedStatuses))}checked="checked"{/if} />{$status.label} ({$status_count.$status_name})</label>&nbsp;
        {/foreach}
        </td>
      </tr>
    </table>
    
    <div class="crm-submit-buttons">
    <input type="submit" class="crm-form-submit" value="{ts domain='org.project60.banking'}Filter{/ts}">
    </div>
  </div>
</form>

<table class="selector row-highlight">
<thead>
  <tr>
    <th class="sorting_disabled"><input type="checkbox" id="banking_selector"></th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Date{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Amount{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Purpose{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Owner{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Status{/ts}</th>
    <th class="sorting_disabled"></th>
  </tr>
</thead>
<tbody>
{if count($lines) > 0}
  {foreach from=$lines item=line}
    <tr>
      <td>
        {if $line.status_name == 'new' || $line.status_name == 'suggestions'}
          <input id="check_{$line.id}" type="checkbox" name="selected_line" onClick="cj('#banking_selector').attr('checked',false);">
        {/if}
      </td>
      <td>{$line.date|crmDate}</td>
      <td>{$line.amount|crmMoney:$line.currency}</td>
      <td>{$line.data_parsed.purpose}</td>
      <td>
        {if ($line.data_parsed.name || $line.data_parsed.email)}
          {$line.data_parsed.name} {$line.data_parsed.email} <br />
        {/if}
        {if ($line.data_parsed.street_address)}
          {$line.data_parsed.street_address}<br />
        {/if}
        {if ($line.data_parsed.postal_code || $line.data_parsed.city)}
          {$line.data_parsed.postal_code} {$line.data_parsed.city}</td>
        {/if}
      </td>
      <td>
        {$line.status}
        {if ($line.status_name == 'suggestions')}
          ({$line.suggestion_count})
        {/if}
      </td>
      <td><span>
        <a class="action-item crm-hover-button" href="{crmURL p="civicrm/banking/review" q="list=`$list`&id=`$line.id`"}" title="{ts domain='org.project60.banking'}Walk through the payments and will show the suggestions and will give you the possibility to manually process the suggestions{/ts}">{ts domain='org.project60.banking'}Review transaction{/ts}</a>
        {if $can_delete}<a class="action-item crm-hover-button" onClick="deleteLine({$line.id});">{ts domain='org.project60.banking'}Delete line{/ts}</a>{/if}
      </span></td>
    </tr>
  {/foreach}
{else}
  <td class="odd" colspan="7">{ts domain='org.project60.banking'}No statement lines found.{/ts}
{/if}
</tbody>
</table>

<div>
  <a id="processButton" class="button" onClick="processSelected()"><span>{ts domain='org.project60.banking'}Analyse &amp; Process{/ts}</span></a>
  <a href="{crmURL p="civicrm/banking/statements"}"  class="button">{ts domain='org.project60.banking'}Back to statement list{/ts}</a>
</div>

<!-- Required JavaScript functions -->
<script language="JavaScript">
var busy_icon = '<img name="busy" src="{$config->resourceBase}i/loading.gif" />';

{literal}
cj("#banking_selector").change(function() {
  checkboxes = document.getElementsByName('selected_line');
  for(var i=0, n=checkboxes.length; i<n; i++) {
    checkboxes[i].checked = this.checked;
  }
});

function getSelected() {
  checkboxes = document.getElementsByName('selected_line');
  var selected = "";
  for(var i=0, n=checkboxes.length; i<n; i++) {
    if (checkboxes[i].checked) {
      id = checkboxes[i].id;
      if (selected.length) selected += ",";
      selected += checkboxes[i].id.substring(6)
      //selected.push(checkboxes[i].id.substring(6));
    }
  }
  return selected;
}

function processSelected() {
  if (cj("#processButton").hasClass('disabled')) return;

  // disable the button
  cj("#processButton").addClass('disabled');

  // mark all selected rows as busy
  var selected_string = getSelected();
  var selected = selected_string.split(',');
  for (var i=0; i<selected.length; i++) {
    cj("#check_" + selected[i]).replaceWith(busy_icon);
  }

  // AJAX call the analyser
  var query = {
    'q': 'civicrm/ajax/rest',
    'sequential': 1,
    'list': selected_string,
    'use_runner': 50, // TODO: setting?
    'back_url': window.location.href
  };
  CRM.api('BankingTransaction', 'analyselist', query,
    {success: function(data) {
        if (!data['is_error']) {
          if ('runner_url' in data.values) {
            // this is a runner -> jump there to execute
            var runner_url = cj("<div/>").html(data.values['runner_url']).text();
            window.location = runner_url;
            return;
          }

          if (!data.values.timed_out) {
            // perfectly normal result, notify user
            {/literal}
            var message = "{ts domain='org.project60.banking'}_1 payments have been processed successfully, _2 had already been completed. The processing took _3s.{/ts}";
            {literal}
            message = message.replace('_1', data.values.processed_count);
            message = message.replace('_2', data.values.skipped_count);
            message = message.replace('_3', data.values.time);
            CRM.alert(message, "info");
            window.setTimeout("location.reload()", 1500);

          } else {
            // this is a time out
            {/literal}
            var message = "{ts domain='org.project60.banking'}<p>_1 out of _2 payments have not been processed!</p><p>If you need to process large amounts of payments manually, please adjust PHPs <code>max_execution_time</code>.</p>{/ts}";
            {literal}
            message = message.replace('_1', data.values.payment_count-data.values.processed_count);
            message = message.replace('_2', data.values.payment_count);
            cj('<div title="{/literal}{ts domain='org.project60.banking'}Process timed out{/ts}{literal}"><span class="ui-icon ui-icon-alert" style="float:left;"></span>' + message + '</div>').dialog({
              modal: true,
              buttons: {
                Ok: function() { location.reload(); }
              }
            });
          }
        } else {
          cj('<div title="{/literal}{ts domain='org.project60.banking'}Error{/ts}{literal}"><span class="ui-icon ui-icon-alert" style="float:left;"></span>' + data['error_message'] + '</div>').dialog({
            modal: true,
            buttons: {
              Ok: function() { location.reload(); }
            }
          });
        }
      }
    }
  );
}

function deleteLine(line_id) {

  CRM.confirm(function()
  {
    // disable ALL buttons
    cj(".button").addClass('disabled');
    cj(".button").attr("onclick","");

    // call the API to delete the items
    var query = {'q': 'civicrm/ajax/rest', 'sequential': 1};
    // set the list or s_list parameter depending on the page mode
    query['list'] = line_id;
    CRM.api('BankingTransaction', 'deletelist', query,
      {success: function(data) {
          if (!data['is_error']) {
            // perfectly normal result, notify user
            {/literal}
            var message = "{ts domain='org.project60.banking'}_1 payments have been deleted.{/ts}";
            {literal}
            message = message.replace('_1', data.values.tx_count);
            message = message.replace('_2', data.values.tx_batch_count);
            CRM.alert(message, "info");
            window.setTimeout("location.reload()", 1500);
          } else {
            cj('<div title="{/literal}{ts domain='org.project60.banking'}Error{/ts}{literal}"><span class="ui-icon ui-icon-alert" style="float:left;"></span>' + data['error_message'] + '</div>').dialog({
              modal: true,
              buttons: {
                Ok: function() { location.reload(); }
              }
            });
          }
        }
      }
    );
  },
  {
    title: {/literal}"{ts domain='org.project60.banking'}Are you sure?{/ts}"{literal},
    message: {/literal}"{ts domain='org.project60.banking'}Do you really want to permanently delete this transaction?{/ts}"{literal}
  });
}
{/literal}
</script>

{/crmScope}
