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

<form action="{$url_action}" method="post" name="DataSource" id="DataSource" enctype="multipart/form-data" >
  <div class="crm-block crm-form-block">
    <h3>{ts domain='org.project60.banking'}Export bank transactions{/ts}:</h3>
    <table class="form-layout">
      <tbody>
        <tr>
          <td style="white-space: nowrap;">{ts domain='org.project60.banking'}Bank Statements{/ts}</td>
          <td style="width:100%">{$txbatch_count}</td>
        </tr>
        <tr>
          <td style="white-space: nowrap;">{ts domain='org.project60.banking'}Bank Transactions{/ts}</td>
          <td style="width:100%">{$tx_count}</td>
        </tr>
      </tbody>
    </table>
    <input type="hidden" name="s_list" value="{$s_list}" />
    <input type="hidden" name="list"   value="{$list}" />
  </div>

  <div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
    <h3>{ts domain='org.project60.banking'}Select Exporter{/ts}</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label">
            <label for="dataSource">{ts domain='org.project60.banking'}Choose configuration{/ts}<span title="This field is required." class="crm-marker">*</span></label>
          </td>
          <td>
            <select class="form-select required" id="banking-exporter-plugin" name="exporter-plugin" onchange="selected_plugin_changed();" 
            {if $page_mode == 'run'}disabled{/if}>
              <option value="-9999">-- {ts domain='org.project60.banking'}select{/ts} --</option>
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
    <h3>{ts domain='org.project60.banking'}Export Options{/ts}</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label">
            <label for="dataSink">{ts domain='org.project60.banking'}Export mode{/ts}<span class="crm-marker">*</span></label>
          </td>
          <td>
            <select id="dataSink" class="form-select required" id="banking-exporter-mode" name="exporter-mode" >
              <option value="1">{ts domain='org.project60.banking'}File Download{/ts}</option>
              <option value="2">{ts domain='org.project60.banking'}Direct Upload{/ts}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="crm-submit-buttons">
    <a class="button back" onclick="cj(this).closest('form').submit();" >
      <span><i class="crm-i fa-download"></i>&nbsp;{ts domain='org.project60.banking'}Export{/ts}</span>
    </a>

    <a class="button back" onclick="parent.history.back();" >
      <span><i class="crm-i fa-backward"></i>{ts domain='org.project60.banking'}&nbsp;Back{/ts}</span>
    </a>
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
    var new_id = parseInt(cj("#banking-exporter-plugin").val());
    var capability = '';
    if (new_id > 0) {
      var capability = capabilities[new_id];
    }
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
