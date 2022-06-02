{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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

{$form.configuration.html}
{$form.plugin_id.html}

<h3>{ts domain='org.project60.banking'}Basic Information{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.name.label}</div>
  <div class="content">{$form.name.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.plugin_type_id.label}</div>
  <div class="content">{$form.plugin_type_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.plugin_class_id.label}</div>
  <div class="content">{$form.plugin_class_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.description.label}</div>
  <div class="content">{$form.description.html}</div>
  <div class="clear"></div>
</div>

<h3>{ts domain='org.project60.banking'}Configuration{/ts}</h3>
<div class="crm-section">
  <div id="help" class="description">
    Configuring CiviBanking plugins is not easy. Maybe have a look at the examples at our <a href="https://github.com/Project60/org.project60.banking/tree/master/configuration_database">configuration database</a>.
  </div>
  <div id="jsoneditor" style="width: 100%; height: 600px;"></div>
  <div align="right">
    <font size="-2" color="gray">
      This brilliant <a href="http://jsoneditoronline.org">JSON editor</a> is being developed by <a href="mailto:wjosdejong@gmail.com">Jos de Jong</a>.
    </font>
  </div>
</div>

{include file="CRM/common/formButtons.tpl" location="bottom"}

<script>
  var type_map = {$type_map};

  {literal}
  // safe entity decoding,
  //   see https://stackoverflow.com/questions/1147359/how-to-decode-html-entities-using-jquery/1395954#1395954
  function decodeEntities(encodedString) {
    let div = document.createElement('div');
    div.innerHTML = encodedString;
    return div.textContent;
  }

  function updateTypeList() {
    var plugin_type_id = cj("#plugin_type_id").val();
    var plugin_class_list = type_map[plugin_type_id];
    // remove all options
    cj("#plugin_class_id").find("option").remove();

    // and add the new ones
    for (plugin_class_id in plugin_class_list) {
      cj("#plugin_class_id").append('<option value="' + plugin_class_id + '">' + plugin_class_list[plugin_class_id] + '</option>');
    }

    // select the first one
    cj("#plugin_class_id").select2().find("option").first().prop('selected', true);
  }
  cj("#plugin_type_id").change(updateTypeList);

  cj(document).ready(function() {
    // create the editor
    var container = document.getElementById('jsoneditor');
    var options = {
      modes: ['text', 'code', 'tree', 'form', 'view'],
      mode: '{/literal}{$json_editor_mode}{literal}',
      ace: null,
      onChange: function() {
        cj("input[name=configuration]").val(JSON.stringify(editor.get()));
      }
    };
    // decode base64 injected configuration
    let configuration = decodeEntities(atob(cj("input[name=configuration]").val()));
    cj("input[name=configuration]").val(configuration);
    var editor = new JSONEditor(container, options, JSON.parse(configuration));
  })
</script>
{/literal}