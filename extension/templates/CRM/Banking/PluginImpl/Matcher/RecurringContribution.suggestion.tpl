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

<div>
  <p>
  {if $recurring_contributions|@count eq 1}
    {assign var=contact_id value=$contacts|@key}
    {assign var=contact value=$contacts.$contact_id}
    {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts domain='org.project60.banking'}Address incomplete{/ts}{/if}{/capture}
    {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}

    {ts 1=$contact_link domain='org.project60.banking'}%1 maintains a matching recurring contribution.{/ts}
    {ts domain='org.project60.banking'}If you confirm this suggestion, the transaction will be recorded as a new installment for this recurring contribution.{/ts}

  {else}
    {assign var=recurring_contribution_count value=$recurring_contributions|@count}
    {if $contacts|@count eq 1}
      {assign var=contact_id value=$contacts|@key}
      {assign var=contact value=$contacts.$contact_id}
      {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts domain='org.project60.banking'}Address incomplete{/ts}{/if}{/capture}
      {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}

      {ts 1=$contact_link 2=$recurring_contribution_count domain='org.project60.banking'}%1 maintains %2 matching recurring contributions.{/ts}

    {else}
      {* compile contact list *}
      {foreach from=$contacts item=contact name=cloop}
        {assign var=contact_id value=$contact.id}
        {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts domain='org.project60.banking'}Address incomplete{/ts}{/if}{/capture}
        {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}

        {if $smarty.foreach.cloop.first}
          {capture assign=contact_list}{$contact_link}{/capture}
        {elseif $smarty.foreach.cloop.last}
          {capture assign=contact_list}{$contact_list} and {$contact_link}{/capture}
        {else}
          {capture assign=contact_list}{$contact_list}, {$contact_link}{/capture}
        {/if}
      {/foreach}

      {ts 1=$contact_link 2=$recurring_contribution_count domain='org.project60.banking'}%1 maintain %2 matching recurring contributions.{/ts}
    {/if}
    {ts domain='org.project60.banking'}If you confirm this suggestion, the transaction will be recorded as new installments for these recurring contributions.{/ts}
  {/if}
  </p>
</div>

<div>
  <table border="1">
    <tbody>
      <tr>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}ID{/ts}</div>
        </td>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}Amount{/ts}</div>
        </td>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}Active Since{/ts}</div>
        </td>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}Cycle{/ts}</div>
        </td>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}Last Installment{/ts}</div>
        </td>
        <td>
          <div class="suggestion-header">{ts domain='org.project60.banking'}Due{/ts}</div>
        </td>
      </tr>
    {foreach from=$recurring_contributions item=recurring_contribution}
      {assign var=recurring_contribution_id value=$recurring_contribution.id}
      {assign var=contact_id value=$recurring_contribution.contact_id}

      {* calculate a more user friendly display of the recurring_contribution transaction interval *}
      {if $recurring_contribution.frequency_unit eq 'month'}
        {if $recurring_contribution.frequency_interval eq 1}
          {capture assign=frequency_words}{ts domain='org.project60.banking'}monthly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 3}
          {capture assign=frequency_words}{ts domain='org.project60.banking'}quarterly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 6}
          {capture assign=frequency_words}{ts domain='org.project60.banking'}semi-annually{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 12}
          {capture assign=frequency_words}{ts domain='org.project60.banking'}annually{/ts}{/capture}
        {else}
          {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval domain='org.project60.banking'}every %1 months{/ts}{/capture}
        {/if}
      {elseif $recurring_contribution.frequency_unit eq 'year'}
        {if $recurring_contribution.frequency_interval eq 1}
          {capture assign=frequency_words}{ts domain='org.project60.banking'}annually{/ts}{/capture}
        {else}
          {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval domain='org.project60.banking'}every %1 years{/ts}{/capture}
        {/if}
      {else}
        {capture assign=frequency_words}{ts domain='org.project60.banking'}on an irregular basis{/ts}{/capture}
      {/if}
      <tr>
        <td>
          {capture assign=contribution_href}{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$recurring_contribution_id&cid=$contact_id"}{/capture}
          <div class="suggestion-value popup"><a href="{$contribution_href}">[{$recurring_contribution.id}]</a></div>
        </td>
        <td>
          <div class="suggestion-value">{$recurring_contribution.amount|crmMoney:$recurring_contribution.currency}</div>
        </td>
        <td>
          <div class="suggestion-value">{$recurring_contribution.start_date|crmDate:$config->dateformatFull}</div>
        </td>
        <td>
          <div class="suggestion-value">{$frequency_words}</div>
        </td>
        <td>
          <div class="suggestion-value">
            {if $recurring_contribution.last_contribution}
            {$recurring_contribution.last_contribution.receive_date|crmDate:$config->dateformatFull}
            {else}
            <strong>{ts domain='org.project60.banking'}None{/ts}</strong>
            {/if}
          </div>
        </td>
        <td>
          <div class="suggestion-value">{$recurring_contribution.due_date|crmDate:$config->dateformatFull}</div>
        </td>
      </tr>
    {/foreach}
    </tbody>
  </table>
</div>
{if $penalties}
<div>
  {ts domain='org.project60.banking'}This suggestion has been downgraded:{/ts}
  <ul>
    {foreach from=$penalties item=reason}
    <li>{$reason}</li>
    {/foreach}
  </ul>
</div>
{/if}