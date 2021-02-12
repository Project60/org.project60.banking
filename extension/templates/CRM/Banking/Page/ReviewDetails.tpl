<table id="btx-details">
    <tr >
        <td>
            <div class="btxheader collapsible" onclick="cj('#extra').toggle();
              cj(this).toggleClass('collapsible-closed');">
                {ts domain='org.project60.banking'}DETAILS{/ts} <span style="font-weight: normal;">{ts domain='org.project60.banking'}(click to see){/ts}</span>
            </div>
        </td>
    </tr>
    <tr style="display: none;" id="extra">
        <td>
            <table class="explorer">
                {foreach from=$extra_data key=k item=v}
                    <tr><td class="xk">{ts domain='org.project60.banking'}{$k}{/ts}</td><td class="btx-detail-entry significant-whitespace">{$v}</td></tr>
                {/foreach}
            </table>
        </td>
    </tr>
</table>
