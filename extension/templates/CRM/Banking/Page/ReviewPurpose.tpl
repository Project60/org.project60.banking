<table id="btx-purpose">
    <tr>
        <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Purpose{/ts}</div>
            <div class="btxvalue btxl">
                {*{$payment_data_raw.move_msg}&nbsp;*}
                {$payment_data_parsed.purpose}
            </div>
        </td>
    </tr>
</table>