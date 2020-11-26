<table id="btx-debtor">
    <tr>
        <td>
            <div class="btxheader">
                {ts domain='org.project60.banking'}DEBTOR INFO{/ts}
            </div>
        </td>
    </tr>
    <tr >
        <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Account{/ts}</div>
            <div class="btxvalue btxl">
                {if $party_ba_references.0}
                    {assign var=ba_contact_id value=$party_ba_references.0.contact_id}
                    {if !$party_ba_references.0.contact_ok}<strike>{/if}
                    <a style="color: {$party_ba_references.0.color};" title="{$party_ba_references.0.reference_type_label}">{$party_ba_references.0.reference}</a>
                    <a style="color: {$party_ba_references.0.color};" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$ba_contact_id"}">[{$party_ba_references.0.probability}, {$ba_contact_id}]</a>
                    {if !$party_ba_references.0.contact_ok}</strike>{/if}
                {elseif $party_account_ref}
                    <span title="{$party_account_reftypename}" class="notfound">{$party_account_ref} ({$party_account_reftype2})</span>
                {else}
                    &nbsp;
                {/if}

            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Address{/ts}</div>
            <div class="btxvalue btxl">
                {$payment_data_parsed.street_address}&nbsp;
            </div>
            <div class="btxlabel">&nbsp;</div>
            <div class="btxvalue btxl">
                {$payment_data_parsed.postal_code} {$payment_data_parsed.city}&nbsp;
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Owner{/ts}</div>
            <div class="btxvalue btxl">
                {$payment_data_parsed.name}{if $payment_data_parsed.email}&nbsp;({$payment_data_parsed.email}){/if}
            </div>
            {if $contact}
                <div class="btxlabel">{ts domain='org.project60.banking'}Contact{/ts}</div>
                <div class="btxvalue btxl">
                    <a href="{$base_url}/civicrm/contact/view?reset=1&cid={$contact.id}">{$contact.display_name}&nbsp;[{$contact.id}]</a>
                </div>
            {/if}
        </td>
    </tr>
</table>
