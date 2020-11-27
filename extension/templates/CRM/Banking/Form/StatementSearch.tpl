{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Zschiedrich                                 |
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

{crmScope extensionKey='org.project60.banking'}
    <br>

    <div class="crm-section">
        <div class="label">{$form.value_date_start.label}</div>
        <div class="content">{$form.value_date_start.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.value_date_end.label}</div>
        <div class="content">{$form.value_date_end.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.booking_date_start.label}</div>
        <div class="content">{$form.booking_date_start.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.booking_date_end.label}</div>
        <div class="content">{$form.booking_date_end.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.minimum_amount.label}</div>
        <div class="content">{$form.minimum_amount.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.maximum_amount.label}</div>
        <div class="content">{$form.maximum_amount.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.status.label}</div>
        <div class="content">{$form.status.html}</div>
        <div class="clear"></div>
    </div>

    {* FOOTER *}
    <br>
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
{/crmScope}
