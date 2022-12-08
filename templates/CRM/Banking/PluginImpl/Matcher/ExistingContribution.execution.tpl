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

{capture assign=contribution_link}{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}{/capture}

<p>
  {if $mode}
    {if $mode == 'cancellation'}
      {ts 1=$contribution_link 2=$contribution_id domain='org.project60.banking'}This transaction cancelled <a href="%1">contribution #%2</a>.{/ts}
      {if $cancel_reason}
        {ts 1=$cancel_reason domain='org.project60.banking'}The recorded cancellation reason was: "%1".{/ts}
      {/if}
      {if $cancel_fee}
        {capture assign=cancel_fee_text}{$cancel_fee|crmMoney}{/capture}
        {ts 1=$cancel_fee_text domain='org.project60.banking'}A cancellation fee of %1 was recorded.{/ts}
      {/if}
    {else}
      {ts 1=$contribution_link 2=$contribution_id domain='org.project60.banking'}This transaction was reconciled with <a href="%1">contribution #%2</a>.{/ts}
    {/if}
  {else}
    {ts 1=$contribution_link 2=$contribution_id domain='org.project60.banking'}This transaction was associated with <a href="%1">contribution #%2</a>.{/ts}
  {/if}
</p>