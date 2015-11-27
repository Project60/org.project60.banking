{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

<div>
  {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
  {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
  {capture assign=contribution_href}{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$recurring_contribution_id&cid=$contact_id"}{/capture}
  {capture assign=date_text}{$recurring_contribution.start_date|crmDate:$config->dateformatFull}{/capture}
  <p>
    {ts 1=$contact_link 2=$recurring_contribution_id 3=$date_text 4=$contribution_href}%1 maintains a <a href="%4">recurring contribution [%2]</a> since %3.{/ts}
    {ts}If you confirm this suggestion, the transaction will be recorded as a new installment for this recurring contribution.{/ts}
  </p>
</div>
<div>
  <table border="1">
    <tbody>
    {foreach from=$recurring_contributions item=recurring_contribution}
      {assign var=recurring_contribution_id value=$recurring_contribution.id}

      {* calculate a more user friendly display of the recurring_contribution transaction interval *}
      {if $recurring_contribution.frequency_unit eq 'month'}
        {if $recurring_contribution.frequency_interval eq 1}
          {capture assign=frequency_words}{ts}monthly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 3}
          {capture assign=frequency_words}{ts}quarterly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 6}
          {capture assign=frequency_words}{ts}semi-annually{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 12}
          {capture assign=frequency_words}{ts}annually{/ts}{/capture}
        {else}
          {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval}every %1 months{/ts}{/capture}
        {/if}
      {elseif $recurring_contribution.frequency_unit eq 'year'}
        {if $recurring_contribution.frequency_interval eq 1}
          {capture assign=frequency_words}{ts}annually{/ts}{/capture}
        {else}
          {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval}every %1 years{/ts}{/capture}
        {/if}
      {else}
        {capture assign=frequency_words}{ts}on an irregular basis{/ts}{/capture}
      {/if}
      <tr>
        <td>
          <div class="btxlabel">{ts}Amount{/ts}:</div>
          <div class="btxvalue">{$recurring_contribution.amount|crmMoney:$recurring_contribution.currency}</div>
        </td>
        <td>
          <div class="btxlabel">{ts}Cycle{/ts}</div>
          <div class="btxvalue">{$frequency_words}</div>
        </td>
        <td>
          <div class="btxlabel">{ts}Last{/ts}:</div>
          <div class="btxvalue">
            {if $recurring_contribution.last_contribution}
            {$recurring_contribution.last_contribution.receive_date|crmDate:$config->dateformatFull}
            {else}
            <strong>{ts}None{/ts}</strong>
            {/if}
          </div>
        </td>
        <td>
          <div class="btxlabel">{ts}Due{/ts}:</div>
          <div class="btxvalue">{$recurring_contribution.due_date|crmDate:$config->dateformatFull}</div>
        </td>
      </tr>
    {/foreach}
    </tbody>
  </table>
</div>
{if $penalties}
<div>
  {ts}This suggestion has been downgraded:{/ts}
  <ul>
    {foreach from=$penalties item=reason}
    <li>{$reason}</li>
    {/foreach}
  </ul>
</div>
{/if}