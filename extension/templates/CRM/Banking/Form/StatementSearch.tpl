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

    <br>

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

    <br>

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

    <br>

    <div class="crm-section">
        <div class="label">{$form.status_select.label}</div>
        <div class="content">{$form.status_select.html}</div>
        <div class="clear"></div>
    </div>

    {foreach item=i from=1|@range:$customDataElementsCount}
        <br>

        {assign var="keyElementName" value="custom_data_key_$i"}
        <div class="crm-section">
            <div class="label">{$form.$keyElementName.label}</div>
            <div class="content">{$form.$keyElementName.html}</div>
            <div class="clear"></div>
        </div>

        {assign var="valueElementName" value="custom_data_value_$i"}
        <div class="crm-section">
            <div class="label">{$form.$valueElementName.label}</div>
            <div class="content">{$form.$valueElementName.html}</div>
            <div class="clear"></div>
        </div>
    {/foreach}

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
            <th data-data="our_account">{ts}Organisation Account{/ts}</th>
            <th data-data="other_account">{ts}Donor Account{/ts}</th>
            <th data-data="amount">{ts}Amount{/ts}</th>
            <th data-data="status">{ts}Status{/ts}</th>
            <th data-data="contact">{ts}Contact (later){/ts}</th>
            <th data-data="review_link">{ts}Review{/ts}</th>
            {*TODO: "review_link" contains the pure link. How can we give it a proper link message like "review transaction? *}
        </tr>
        </thead>
    </table>
{/crmScope}

<script>
    {literal}
        /**
         * Refresh the AJAX table link to reflect the current settings.
         *
         * @returns {string}
         */
        function banking_transaction_search_update_table_link()
        {
            const pickers = [];

            CRM.$('input, select').each(
                function (i, element)
                {
                    let value = CRM.$(this).val();

                    if (Array.isArray(value))
                    {
                        value = value.join(',');
                    }

                    pickers.push(element.id + '=' + value);
                }
            );

            let url = CRM.vars['banking_transaction_search'].data_url + '?' + pickers.join('&');

            CRM.$('table.banking-transaction-search-result').data(
                {
                    "ajax": {
                        "url": url,
                    }
                }
            );

            return url;
        }

        /**
         * Trigger a refresh of the AJAX table
         */
        function banking_transaction_search_refresh_table()
        {
            CRM.$('table.banking_transaction_search').DataTable().ajax.url(banking_transaction_search_update_table_link()).draw();
        }

        // trigger this function
        (function ($) {
            banking_transaction_search_update_table_link();
        })(CRM.$);
        cj("#date").change(banking_transaction_search_update_table_link);
    {/literal}
</script>
