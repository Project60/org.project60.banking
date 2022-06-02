{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

{assign var=contact_id value=$contact.id}
{assign var=membership_id value=$membership.id}

{* calculate a more user friendly display of the membership transaction interval *}
{if $membership_type.duration_unit eq 'month'}
  {if $membership_type.duration_interval eq 1}
    {capture assign=frequency_words}{ts domain='org.project60.banking'}monthly{/ts}{/capture}
  {elseif $membership_type.duration_interval eq 3}
    {capture assign=frequency_words}{ts domain='org.project60.banking'}quarterly{/ts}{/capture}
  {elseif $membership_type.duration_interval eq 6}
    {capture assign=frequency_words}{ts domain='org.project60.banking'}semi-annually{/ts}{/capture}
  {elseif $membership_type.duration_interval eq 12}
    {capture assign=frequency_words}{ts domain='org.project60.banking'}annually{/ts}{/capture}
  {else}
    {capture assign=frequency_words}{ts 1=$membership_type.duration_interval domain='org.project60.banking'}every %1 months{/ts}{/capture}
  {/if}
{elseif $membership_type.duration_unit eq 'year'}
  {if $membership_type.duration_interval eq 1}
    {capture assign=frequency_words}{ts domain='org.project60.banking'}annually{/ts}{/capture}
  {else}
    {capture assign=frequency_words}{ts 1=$membership_type.duration_interval domain='org.project60.banking'}every %1 years{/ts}{/capture}
  {/if}
{else}
  {capture assign=frequency_words}{ts domain='org.project60.banking'}on an irregular basis{/ts}{/capture}
{/if}

<div>
  {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts domain='org.project60.banking'}Address incomplete{/ts}{/if}{/capture}
  {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
  {assign var=status_text value=$membership_status.label}
  {capture assign=type_link}<a title="{$membership_type.description}" href="{crmURL p="civicrm/contact/view/membership" q="action=view&reset=1&cid=$contact_id&id=$membership_id&context=membership&selectedChild=member"}">"{$membership.title}"</a>{/capture}
  {capture assign=date_text}{$membership.start_date|crmDate:$config->dateformatFull}{/capture}
  <p>
    {ts 1=$contact_link 2=$status_text 3=$type_link 4=$date_text domain='org.project60.banking'}%1 has a <i>%2</i> membership of type %3 since %4.{/ts}
    {ts domain='org.project60.banking'}If you confirm this suggestion, the transaction will be recorded as a fee payment for this membership.{/ts}
  </p>
</div>
<div>
{if $last_fee.id}
  <table border="1">
    <tbody>
      <tr>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Last{/ts}:</div>
          <div class="btxvalue">{$last_fee.total_amount|crmMoney:$last_fee.currency}</div>
        </td>
        <td>
          {capture assign=day_count}{$last_fee.days|abs}{/capture}
          {if $last_fee.days gt 0}
            {capture assign=last_fee_days}{ts 1=$day_count domain='org.project60.banking'}(%1 days earlier){/ts}{/capture}
          {else}
            {capture assign=last_fee_days}{ts 1=$day_count domain='org.project60.banking'}(%1 days later){/ts}{/capture}
          {/if}
          <div class="btxlabel">{ts domain='org.project60.banking'}Paid{/ts}:</div>
          <div class="btxvalue">{$last_fee.receive_date|crmDate:$config->dateformatFull} {$last_fee_days}</div>
        </td>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Type{/ts}:</div>
          <div class="btxvalue">{$membership_type.period_type}</div>
        </td>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Cycle{/ts}:</div>
          <div class="btxvalue">{$frequency_words}</div>
        </td>
      </tr>
    </tbody>
  </table>
{else}
  <table border="1">
    <tbody>
      <tr>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Last{/ts}:</div>
          <div class="btxvalue"><strong>{ts domain='org.project60.banking'}None{/ts}</strong></div>
        </td>
        <td>
          {capture assign=day_count}{$membership.days|abs}{/capture}
          {if $membership.days gt 0}
            {capture assign=membership_days}{ts 1=$day_count domain='org.project60.banking'}(%1 days earlier){/ts}{/capture}
          {else}
            {capture assign=membership_days}{ts 1=$day_count domain='org.project60.banking'}(%1 days later){/ts}{/capture}
          {/if}
          <div class="btxlabel">{ts domain='org.project60.banking'}Due{/ts}:</div>
          <div class="btxvalue">{$membership.start_date|crmDate:$config->dateformatFull} {$membership_days}</div>
        </td>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Fee{/ts}:</div>
          <div class="btxvalue">{$membership_type.minimum_fee|crmMoney} ({$membership.percentage_of_minimum}%)</div>
        </td>
        <td>
          <div class="btxlabel">{ts domain='org.project60.banking'}Cycle{/ts}</div>
          <div class="btxvalue">{$frequency_words}</div>
        </td>
      </tr>
    </tbody>
  </table>
{/if}
</div>
