{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2023 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
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

{if $error}
<div>
  {ts domain='org.project60.banking'}An error has occurred:{/ts} {$error}<br/>
  {ts domain='org.project60.banking'}This suggestion is possibly outdated. Please try and analyse this transaction again.{/ts}
</div>
{else}
<div>
  {ts domain='org.project60.banking'}The following contribution will be created:{/ts}
  <br/>
  <div>
    <table border="1" style="empty-cells : hide;">
      <tbody>
        <tr>
        {if $is_organization}
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Organization Name{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contact.organization_name}</div>
          </td>
        {else}
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Formal Title{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contact.formal_title}</div>
          </td>
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}First Name{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contact.first_name}</div>
          </td>
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Last Name{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contact.last_name}</div>
          </td>
        {/if}
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Campaign{/ts}:&nbsp;</div>
            <div class="btxvalue">{$campaign.title}</div>
          </td>
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Amount{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.total_amount|crmMoney:$contribution.currency}</div>
          </td>
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Date{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.receive_date|crmDate:$config->dateformatFull}</div>
          </td>
          <td>
            <div class="btxlabel">{ts domain='org.project60.banking'}Type{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.financial_type}</div>
          </td>
        </tr>
        {if $campaign or $source}
        <tr>
          <td colspan="2">
            <div class="btxlabel">{ts domain='org.project60.banking'}Campaign{/ts}:&nbsp;</div>
            <div class="btxvalue">{$campaign.title}</div>
          </td>
          <td colspan="2">
            <div class="btxlabel">{$source_label}:&nbsp;</div>
            <div class="btxvalue">{$source}</div>
          </td>
        </tr>
        {/if}
      </tbody>
    </table>
  </div>
</div>
{/if}
