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

<form action="{$url_action}" method="post" name="DataSource" id="DataSource" enctype="multipart/form-data" >
  <div class="crm-block crm-form-block">
    <h3>{ts}Export bank transactions{/ts}:</h3>
    <table class="form-layout">
      <tbody>
        <tr>
          <td style="white-space: nowrap;">{ts}Bank Statements{/ts}</td>
          <td style="width:100%">{$txbatch_count}</td>
        </tr>
        <tr>
          <td style="white-space: nowrap;">{ts}Bank Transactions{/ts}</td>
          <td style="width:100%">{$tx_count}</td>
        </tr>
      </tbody>
    </table>
    <input type="hidden" name="s_list" value="{$s_list}" />
    <input type="hidden" name="list"   value="{$list}" />
  </div>

  <div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
    <h3>{ts}Select Exporter{/ts}</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label">
            <label for="dataSource">{ts}Choose configuration{/ts}<span title="This field is required." class="crm-marker">*</span></label>
          </td>
          <td>
            <select class="form-select required" id="banking-exporter-plugin" name="exporter-plugin" onchange="selected_plugin_changed();" 
            {if $page_mode == 'run'}disabled{/if}>
              <option value="-9999">-- {ts}select{/ts} --</option>
            {foreach from=$plugin_list item=field key=fieldName}
              <option value="{$field->id}" {if $plugin_id == $field->id} selected{/if}>{$field->name}</option>
            {/foreach}
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="crm-block crm-form-block crm-import-datasource-form-block" id="import options">
    <h3>{ts}Export Options{/ts}</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label">
            <label for="dataSink">{ts}Export mode{/ts}<span class="crm-marker">*</span></label>
          </td>
          <td>
            <select id="dataSink" class="form-select required" id="banking-exporter-mode" name="exporter-mode" >
              <option value="1">{ts}File Download{/ts}</option>
              <option value="2">{ts}Direct Upload{/ts}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="crm-submit-buttons">
    <span class="crm-button crm-button-type-upload">
      <input type="submit" value="{ts}Export{/ts}" class="validate form-submit default">
    </span>
    <span class="crm-button crm-button-type-upload">
      <input id="banking-exporter-back" type="button" value="{ts}Back{/ts}" hidden="1" onClick="banking_exporter_goback();">
    </span>
  </div>
</form>

{* logic for capabilities (file/stream) *}
<script type="text/javascript">
  {literal} 
    var capabilities = {
  {/literal}
  {foreach from=$plugin_capabilities item=capability key=pid}
    {$pid} : '{$capability}',
  {/foreach}
  {literal}
    };

  function selected_plugin_changed() {
    var new_id = cj("#banking-exporter-plugin").val();
    var capability = '';
    if (new_id>0) {
      var capability = capabilities[new_id];
    }
    cj("#dataSink [value=1]").attr('disabled', !capability.contains('F'));
    cj("#dataSink [value=2]").attr('disabled', !capability.contains('S'));
    if (capability == 'F') {
      cj("#dataSink [value=1]").attr('selected', true);
    } else if (capability == 'S') {
      cj("#dataSink [value=2]").attr('selected', true);
    }
  }
  {/literal}

  // call once for inital selection
  selected_plugin_changed();
</script>


{* logic for back button *}
<script type="text/javascript">
{literal}
var back_url = document.referrer;
if (back_url) {
  cj("#banking-exporter-back").show();
}

function banking_exporter_goback() {
  window.location = back_url;
  return false;
}
{/literal}
</script>