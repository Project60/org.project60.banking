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

{literal}
  <style>
    table.log tr td {
      padding: 1px 8px; 
      font-family: Courier;
      letter-spacing: -1px;
      font-size: 12px;
      line-height: 1.2em;
      background-color: #f7f7f7;
    }
  </style>
{/literal}
  
<form action="{$url_action}" method="post" name="DataSource" id="DataSource" enctype="multipart/form-data" >
  <div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
    <h3>{ts}Export bank transactions{/ts}:</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td>{ts}Bank Statements{/ts}</td>
        </tr>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td>{ts}Bank Transactions{/ts}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
    <h3>{ts}Select Exporter{/ts}</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label"><label for="dataSource">{ts}Choose configuration{/ts}<span title="This field is required." class="crm-marker">*</span></label>
          </td>
          <td>
            <select class="form-select required" id="banking-importer-plugin" name="importer-plugin" onchange="selected_plugin_changed(this.value);" 
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
        <td>
          <input type="checkbox" class="form-checkbox" value="on" name="dry_run" id="dry_run"
          {if $page_mode == 'run'} disabled {/if}
        {if $dry_run == 'on'} checked {/if}>
      {ts}Dry run{/ts}</input>
    </td>
  </tr>
  <tr class="crm-import-datasource-form-block-dataSource">
    <td>
      <input type="checkbox" disabled class="form-checkbox" value="on" name="process" id="process" 
      {if $page_mode == 'run'} disabled {/if}
    {if $process == 'on'} checked {/if}>
  {ts}Process payments right away{/ts}</input>
</td>
</tr>
</tbody>
</table>
</div>


<div class="crm-submit-buttons">
  {if $page_mode != 'config'}
    <a class="button" href="{$url_payments}">
      <span align="right"><div class="icon details-icon"></div>{ts}See Results{/ts}</span>
    </a>
    <a class="button" href="{$url_action}">
      <span align="right"><div class="icon details-icon"></div>{ts}Import More{/ts}</span>
    </a>
  {/if}
</div>

</form>

