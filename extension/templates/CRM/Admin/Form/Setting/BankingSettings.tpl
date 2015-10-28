{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

<h3>{ts}General Settings{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.menu_position.label}</div>
  <div class="content">{$form.menu_position.html}</div>
  <div class="clear"></div>
</div>

<br/>
<h3>{ts}Bank Account Settings{/ts}</h3>

<div class="crm-section">
  <div class="label">{$form.reference_validation.label}</div>
  <div class="content">{$form.reference_validation.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.reference_normalisation.label}</div>
  <div class="content">{$form.reference_normalisation.html}</div>
  <div class="clear"></div>
</div>

<br/>
{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
