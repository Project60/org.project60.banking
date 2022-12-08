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

{* compile contribution list *}
{assign var=contribution_list value=''}
{assign var=rcontribution_list value=''}

{foreach from=$contributions item=contribution name=cloop}
  {assign var=contribution_id value=$contribution.id}
  {assign var=rcontribution_id value=$contribution.contribution_recur_id}
  {assign var=contact_id value=$contribution.contact_id}
  
  {capture assign=contribution_link}<a href="{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}">[{$contribution_id}]</a>{/capture}
  {capture assign=rcontribution_link}<a href="{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$rcontribution_id&cid=$contact_id"}">[{$rcontribution_id}]</a>{/capture} 

  {if $smarty.foreach.cloop.first}
    {capture assign=contribution_list}{$contribution_link}{/capture}
    {capture assign=rcontribution_list}{$rcontribution_link}{/capture}
  {elseif $smarty.foreach.cloop.last}
    {capture assign=contribution_list}{$contribution_list} and {$contribution_link}{/capture}
    {capture assign=rcontribution_list}{$rcontribution_list} and {$rcontribution_link}{/capture}
  {else}
    {capture assign=contribution_list}{$contribution_list}, {$contribution_link}{/capture}
    {capture assign=rcontribution_list}{$rcontribution_list}, {$rcontribution_link}{/capture}
  {/if}
{/foreach}

<p>
  {ts 1=$contribution_list 2=$rcontribution_list domain='org.project60.banking'}This transaction has created contribution %1 for recurring contribution %2{/ts}
</p>
