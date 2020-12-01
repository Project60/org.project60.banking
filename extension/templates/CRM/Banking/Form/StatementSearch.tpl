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
            <th data-data="amount">{ts}Amount{/ts}</th>
            <th data-data="contact">{ts}Contact{/ts}</th>
            <th data-data="status">{ts}Status{/ts}</th>
            {*
                // TODO: Weitere sinnvolle Felder.
                // TODO: Link "Transaktion prüfen" (es genügt nur die id, Liste überflüssig)
                // TODO: Link "Transaktion löschen"
            *}
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
            let picker_count = cj("[name^=date_]").length;
            let pickers = [];

            for (let i = 1; i <= picker_count; i++)
            {
                let selector = "[name^=date_" + i + "]";

                if (cj(selector).val().length > 0)
                {
                    pickers.push(cj(selector).val());
                }
            }

            let url = CRM.vars['banking_transaction_search'].data_url + '&pickers=' + pickers.join(',');

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
