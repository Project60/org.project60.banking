{crmScope extensionKey='org.project60.banking'}

<form action="" method="get">
  <div class="crm-block crm-form-block">
    <h3>{ts domain='org.project60.banking'}Showing statements for{/ts}</h3>
    <table class="form-layout">
      <tbody>
      <tr>
        <td>
          <label><input type="checkbox" {if $include_completed}checked="checked"{/if} name="include_completed" value="1" />&nbsp;{ts domain='org.project60.banking'}Include completed statements{/ts}</label>
      </tr>
      <tr>
        <td>
          <label for="target_ba_id">{ts domain='org.project60.banking'}Bank account{/ts}</label>
          <select name="target_ba_id" class="crm-select2">
            <option value="" {if $target_ba_id == -1}selected="selected"{/if}>{ts domain='org.project60.banking'}all accounts{/ts}</option>
            {foreach from=$target_accounts item=ba_name key=ba_id}
              <option value="{$ba_id}" {if $ba_id == $target_ba_id && $target_ba_id >= 0}selected="selected"{/if}>{$ba_name}</option>
            {/foreach}
          </select>
        </td>
      </tr>
      <tr>
        <td>
          <label for="date">{ts domain='org.project60.banking'}Date{/ts}</label>
          <input formattype="searchDate" startoffset="{$date_attributes.startOffset}" endoffset="{$date_attributes.endOffset}" format="{$date_attributes.format}" value="{$date}" name="date" id="date" class="crm-form-text" type="text">
          <input type="text" name="date_display" id="date_display" class="dateplugin" autocomplete="off"/>
          <a href="#" class="crm-hover-button crm-clear-link" title="{ts domain='org.project60.banking'}Clear{/ts}"><i class="crm-i fa-times"></i></a>
          <script type="text/javascript">
            {literal}
            CRM.$(function($) {
              // Workaround for possible duplicate ids in the dom - select by name instead of id and exclude already initialized widgets
              var $dateElement = $('input[name=date_display].dateplugin:not(.hasDatepicker)');
              if (!$dateElement.length) {
                return;
              }
              var $timeElement = $();
              var currentYear = new Date().getFullYear(),
              $originalElement = $('#date').hide(),
              date_format = $originalElement.attr('format'),
              altDateFormat = 'mm/dd/yy';

              if ( !( ( date_format == 'M yy' ) || ( date_format == 'yy' ) || ( date_format == 'yy-mm' ) ) ) {
                $dateElement.addClass( 'dpDate' );
              }

              var yearRange = (currentYear - parseInt($originalElement.attr('startOffset'))) +
                ':' + currentYear + parseInt($originalElement.attr('endOffset')),
              startRangeYr = currentYear - parseInt($originalElement.attr('startOffset')),
              endRangeYr = currentYear + parseInt($originalElement.attr('endOffset'));

              $dateElement.datepicker({
                closeAtTop: true,
                dateFormat: date_format,
                changeMonth: (date_format.indexOf('m') > -1),
                changeYear: (date_format.indexOf('y') > -1),
                altField: $originalElement,
                altFormat: altDateFormat,
                yearRange: yearRange,
                minDate: new Date(startRangeYr, 1 - 1, 1),
                maxDate: new Date(endRangeYr, 12 - 1, 31)
            });

            // format display date
            var displayDateValue = $.datepicker.formatDate(date_format, $.datepicker.parseDate(altDateFormat, $originalElement.val()));
            //support unsaved-changes warning: CRM-14353
            $dateElement.val(displayDateValue).data('crm-initial-value', displayDateValue);

            // Add clear button
            $($timeElement).add($originalElement).add($dateElement).on('blur change', function() {
              var vis = $dateElement.val() || $timeElement.val() ? '' : 'hidden';
              $dateElement.siblings('.crm-clear-link').css('visibility', vis);
            });
            $originalElement.change();
          });

          {/literal}
          </script>
        </td>
      </tr>
      </tbody>
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
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Starting date{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Ending date{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Account{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Amount{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Reference{/ts}</th>
    <th class="sorting_disabled">{ts domain='org.project60.banking'}Sequence{/ts}</th>
    <th class="sorting_disabled" title="{ts domain='org.project60.banking'}(new / suggestions / processed / ignored){/ts}">{ts domain='org.project60.banking'}Transactions{/ts}</th>
    <th class="sorting_disabled"></th>
  </tr>
</thead>
<tbody>
{if count($statements) > 0}
  {foreach from=$statements item=statement}
    <tr class="{cycle values="odd-row,even-row"}">
      <td>
        {if $statement.status.new > 0 || $statement.status.suggestions > 0}
          <input id="check_{$statement.id}" type="checkbox" name="selected_statement" onClick="cj('#banking_selector').attr('checked',false);">
        {/if}
      </td>
      <td>{$statement.starting_date|crmDate}</td>
      <td>{$statement.ending_date|crmDate}</td>
      <td>{$statement.target}</td>
      <td>{$statement.total|crmMoney:$statement.currency}</td>
      <td>{$statement.reference}</td>
      <td>{$statement.sequence}</td>
      <td title="{ts domain='org.project60.banking'}(new / suggestions / processed / ignored){/ts}">{$statement.count}<br>
        ({$statement.status.new} / {$statement.status.suggestions} / {$statement.status.processed} / {$statement.status.ignored})
      </td>
      <td><span>
        <a class="action-item crm-hover-button" href="{crmURL p="civicrm/banking/review" q="s_list=`$statement.id`"}" title="{ts domain='org.project60.banking'}Walk through the payments and will show the suggestions and will give you the possibility to manually process the suggestions{/ts}">{ts domain='org.project60.banking'}Review statements{/ts}</a>
        <a class="action-item crm-hover-button" href="{crmURL p="civicrm/banking/statements/lines" q="s_id=`$statement.id`"}&reset=1">{ts domain='org.project60.banking'}List lines{/ts}</a>
        {if $can_delete}<a class="action-item crm-hover-button" onClick="deleteStatement({$statement.id});">{ts domain='org.project60.banking'}Delete statement{/ts}</a>{/if}
      </span></td>
    </tr>
  {/foreach}
{else}
  <td class="odd" colspan="9">{ts domain='org.project60.banking'}No statements found.{/ts}
{/if}
</tbody>
</table>

{include file="CRM/common/pager.tpl" location="bottom"}

<div>
  <a id="processButton" class="button" onClick="processSelected()"><span>{ts domain='org.project60.banking'}Analyse &amp; Process{/ts}</span></a>
  <a id="exportButton"  class="button" onClick="callWithSelected('{$url_export_selected_payments}', false)"><span>{ts domain='org.project60.banking'}Export{/ts}</span></a>
  <a href="{crmURL p="civicrm/banking/import"}"  class="button">{ts domain='org.project60.banking'}Import{/ts}</a>
</div>

<!-- Required JavaScript functions -->
<script language="JavaScript">
var busy_icon = '<img name="busy" src="{$config->resourceBase}i/loading.gif" />';

{literal}
cj("#banking_selector").change(function() {
  checkboxes = document.getElementsByName('selected_statement');
  for(var i=0, n=checkboxes.length; i<n; i++) {
    checkboxes[i].checked = this.checked;
  }
});

function getSelected() {
  checkboxes = document.getElementsByName('selected_statement');
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

function callWithSelected(url, forced) {
  var selected = getSelected();
  if (selected || forced) {
    location.href = url.replace("__selected__", selected);
  } else {
    {/literal}
    var message = "{ts domain='org.project60.banking'}Please select one or more items{/ts}";
    {literal}
    window.alert(message);
  }
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
    's_list': selected_string,
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

function deleteStatement(statement_id) {

  CRM.confirm(function()
  {
    // disable ALL buttons
    cj(".button").addClass('disabled');
    cj(".button").attr("onclick","");

    // call the API to delete the items
    var query = {'q': 'civicrm/ajax/rest', 'sequential': 1};
    // set the list or s_list parameter depending on the page mode
    query['s_list'] = statement_id;
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
    message: {/literal}"{ts domain='org.project60.banking'}Do you really want to permanently delete the statement?{/ts}"{literal}
  });
}
{/literal}
</script>

{/crmScope}
