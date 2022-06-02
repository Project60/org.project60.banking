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

<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="banking-transaction-search">
    <div class="crm-accordion-header">
        {ts}Search Criteria{/ts}
    </div>
    <div class="crm-accordion-body">
        <div class="crm-section">
            <div class="label">{$form.status_select.label}</div>
            <div class="content">{$form.status_select.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section">
            <div class="label">{ts}Booking Date{/ts}</div>
            <div class="content">{$form.booking_date_start.html}&nbsp;&hellip;&nbsp;&nbsp;{$form.booking_date_end.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section">
            <div class="label">{ts}Value Date{/ts}</div>
            <div class="content">{$form.value_date_start.html}&nbsp;&hellip;&nbsp;&nbsp;{$form.value_date_end.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section">
            <div class="label">{ts}Amount{/ts}</div>
            <div class="content">{$form.minimum_amount.html}&nbsp;&hellip;&nbsp;&nbsp;{$form.maximum_amount.html}</div>
            <div class="clear"></div>
        </div>

    {foreach item=i from=1|@range:$customDataElementsCount}
        <div class="custom_value_search custom_value_search-{$i}">
            {assign var="keyElementName" value="custom_data_key_name_$i"}
            {assign var="listElementName" value="custom_data_key_list_$i"}
            {assign var="valueElementName" value="custom_data_value_$i"}
            <div class="crm-section">
                <div class="label">{ts 1=$i}Additional Criteria %1{/ts}</div>
                <div class="content">{$form.$listElementName.html}&nbsp;{$form.$keyElementName.html}</div>
                <div class="clear"></div>
            </div>

            <div class="crm-section">
                <div class="label">{$form.$valueElementName.label}</div>
                <div class="content">{$form.$valueElementName.html}</div>
                <div class="clear"></div>
            </div>
        </div>
    {/foreach}
    </div>

    {* Buttons *}
    <br>
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

    {* Transactions *}
    <table class="banking-transaction-search-result crm-ajax-table">
        <thead>
        <tr>
            <th data-data="date">{ts}Date{/ts}</th>
            <th data-data="our_account" data-orderable="false">{ts}Organisation Account{/ts}</th>
            <th data-data="other_account" data-orderable="false">{ts}Donor Account{/ts}</th>
            <th data-data="amount">{ts}Amount{/ts}</th>
            <th data-data="status">{ts}Status{/ts}</th>
            <th data-data="purpose" data-orderable="false">{ts}Purpose{/ts}</th>
            <th data-data="review_link">{ts}Transaction{/ts}</th>
        </tr>
        </thead>
    </table>
{/crmScope}