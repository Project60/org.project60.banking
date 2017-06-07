{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017 SYSTOPIA                            |
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

<h3>{ts}Basic Information{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.name.label}</div>
  <div class="content">{$form.name.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.plugin_class_id.label}</div>
  <div class="content">{$form.plugin_class_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.plugin_type_id.label}</div>
  <div class="content">{$form.plugin_type_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.description.label}</div>
  <div class="content">{$form.description.html}</div>
  <div class="clear"></div>
</div>

<h3>{ts}Configuration{/ts}</h3>
<div class="crm-section">
  <div id="help" class="description">
    Configuring CiviBanking plugins is not easy. Maybe have a look at the examples at our <a href="https://github.com/Project60/org.project60.banking/tree/master/configuration_database">configuration database</a>.
  </div>
  <div id="jsoneditor"></div>
  <div align="right">
    <font size="-2" color="gray">
      This brilliant <a href="http://jsoneditoronline.org">JSON editor</a> is being developed by <a href="mailto:wjosdejong@gmail.com">Jos de Jong</a>.
    </font>
  </div>
</div>

{include file="CRM/common/formButtons.tpl" location="bottom"}

{literal}
<script>
  cj(document).ready(function() {
    // create the editor
    var container = document.getElementById('jsoneditor');
    var options = {
      modes: ['text', 'code', 'tree', 'form', 'view'],
      mode: 'form',
      ace: null
    };
    var configuration = cj("input[name=configuration]").val();
    var editor = new JSONEditor(container, options, JSON.parse(configuration));
  })
</script>
{/literal}