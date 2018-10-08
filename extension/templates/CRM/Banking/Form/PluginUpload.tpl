{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
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

{$form.pid.html}

<div class="crm-section">
  <div class="label">{$form.config_files.label}</div>
  <div class="content">{$form.config_files.html}</div>
  <div class="clear"></div>
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{if $is_import}
  {literal}
    <script type="application/javascript">
      cj(document).ready(function() {
          cj("#config_files").closest("form").attr('enctype' ,'multipart/form-data');
          cj("#config_files").attr('name', 'config_files[]');
      });
    </script>
  {/literal}
{/if}