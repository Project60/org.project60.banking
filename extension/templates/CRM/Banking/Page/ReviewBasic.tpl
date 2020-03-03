<table id="btx-amt">
    <tr>
        <td>
            <div class="btxheader">
                {ts domain='org.project60.banking'}BASIC INFO{/ts}
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="btxvalue btxamt{if $payment->amount lt 0} btxamtneg{/if}{if $payment->amount gte 10000} btxamtlarge{/if}{if $payment->amount lte -10000} btxamtlarge{/if}">
                <div class="btxcurr">{$payment->currency}</div>
                {$payment->amount}
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Booking{/ts}</div>
            <div class="btxvalue btxc">
                {$payment->booking_date|truncate:10:''}
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Value{/ts}</div>
            <div class="btxvalue btxc">
                {$payment->value_date|truncate:10:''}
            </div>
        </td>
    </tr>
</table>