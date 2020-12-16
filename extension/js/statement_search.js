/*-------------------------------------------------------+
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
+--------------------------------------------------------*/

/**
 * Refresh the AJAX table link to reflect the current settings.
 *
 * @returns {string}
 */
function banking_transaction_search_update_table_link()
{
    let search_criteria = [];

    // build query URL

    // add default fields
    for (let index in CRM.vars.banking_txsearch_basic_fields) {
        let field_name = CRM.vars.banking_txsearch_basic_fields[index];
        let field_value = '';
        if (field_name === 'status_select') {
            field_value = cj([name=status_select]).val();
            if (Array.isArray(field_value)) {
                field_value = field_value.join(',');
            }
        } else {
            field_value = cj('[name=' + field_name + ']').val();
        }
        if (field_value) {
            search_criteria.push(field_name + "=" + encodeURIComponent(field_value));
        }
    }

    // add custom fields
    let custom_search_params = [];
    cj('div.custom_value_search').each(function() {
        let key = cj(this).find('[name^=custom_data_key_name_]').val();
        let value = cj(this).find('[name^=custom_data_value_]').val();
        if (key != '' && value != '') {
            search_criteria.push(encodeURIComponent(key) + "=" + encodeURIComponent(value));
            custom_search_params.push(encodeURIComponent(key));
        }
    });
    search_criteria.push("custom_parameters=" + custom_search_params.join(','));

    let url = CRM.vars['banking_transaction_search'].data_url + '?' + search_criteria.join('&');
    CRM.$('table.banking-transaction-search-result').data(
        {
            "ajax": {
                "url": url,
            }
        }
    );

    return url;
}

// /**
//  * Trigger a refresh of the AJAX table
//  */
// function banking_transaction_search_refresh_table()
// {
//     CRM.$('table.banking_transaction_search').DataTable().ajax.url(banking_transaction_search_update_table_link()).draw();
// }

/**
 * Update the custom key name show/hide function
 */
function banking_transaction_search_update_custom_criteria(event) {
    let section = null;
    if (event) {
        section = cj(event.target).closest("div.custom_value_search");
    } else {
        section = cj("div.custom_value_search");
    }

    section.each(function() {
        let key_name = cj(this).find('[name^=custom_data_key_name_]');
        let selection = cj(this).find('[name^=custom_data_key_list_]');
        if (selection.val() === '__other__') {
            key_name.show();
        } else {
            key_name.val(selection.val());
            key_name.hide();
        }
    });
    banking_transaction_search_hide_empty_criteria();
}

/**
 * Will hide all the unused custom search criteria fields
 */
function banking_transaction_search_hide_empty_criteria() {
    // first: show all
    cj('[name^=custom_data_value_]')
        .closest("div.custom_value_search")
        .show();

    // then hide all empty ones except the first
    cj('[name^=custom_data_value_]')
        .filter(function() {return !cj(this).val();})
        .slice(1) // skip the first one
        .closest("div.custom_value_search")
        .hide();
}



// search form amenities
cj(document).ready(function() {
    // initial setup for all elements
    banking_transaction_search_update_custom_criteria(null);
    banking_transaction_search_hide_empty_criteria();

    // add trigger to change events
    cj("select[name^=custom_data_key_list_]").change(banking_transaction_search_update_custom_criteria);
    cj("[name^=custom_data_value_]").change(banking_transaction_search_hide_empty_criteria);
    cj("[name^=custom_data_value_]").click(function(event) {
        // show next field if you click on it
        cj(event.target)
            .closest("div.custom_value_search")
            .next()
            .show();
    });

    // run query (with delay)
    setTimeout(banking_transaction_search_update_table_link, 100);
});
