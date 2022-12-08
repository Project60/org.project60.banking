<table id="btx-info">
    <tr>
        <td>
            <div class="btxheader">
                {ts domain='org.project60.banking'}TRANSACTION INFO{/ts}
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="btxvalue">
                {$my_bao->description}
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Stmt. #{/ts}</div>
            <div class="btxvalue btxc">
                {$payment->tx_batch_id}&nbsp;
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Trans. #{/ts}</div>
            <div class="btxvalue btxc">
                {$payment->id}&nbsp;
            </div>
            <div class="btxlabel">{ts domain='org.project60.banking'}Status{/ts}</div>
            <div class="btxvalue btxc">
                {$btxstatus.label}
            </div>
        </td>
    </tr>
</table>