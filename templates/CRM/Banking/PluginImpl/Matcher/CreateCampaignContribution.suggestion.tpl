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

{crmScope extensionKey='org.project60.banking'}
{assign var=contact_id value=$contact.id}
{capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
{capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
{capture assign=activity_link}<a title="{$activity_title}" href="{$activity_url}">{$activity_title} [{$activity_id}]</a>{/capture}

{if $error}
<div>
  {ts}An error has occurred:{/ts} {$error}<br/>
  {ts}This suggestion is possibly outdated. Please try and analyse this transaction again.{/ts}
</div>
{else}
<div>
  {ts 1=$activity_link}Based on activity '%1', the following contribution will be created:{/ts}
  <br/>
  <div>
    <table border="1" style="empty-cells : hide;">
      <tbody>
        <tr>
          <td>
            <div class="btxlabel">{ts}Donor{/ts} </div>
            <div class="btxvalue">{$contact_link}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Amount{/ts} </div>
            <div class="btxvalue">{$contribution.total_amount|crmMoney:$contribution.currency}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Campaign{/ts}: </div>
            <div class="btxvalue">{$campaign_name}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Date{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.receive_date|crmDate:$config->dateformatFull}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Type{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.financial_type}</div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  {if $penalty_applied}
    <br/>
    {ts 1=$penalty_applied}Caution: this contact has <b>active recurring contributions</b>, so the score of this suggestion has been reduced by %1%.{/ts}
  {/if}
</div>
{/if}
{/crmScope}
