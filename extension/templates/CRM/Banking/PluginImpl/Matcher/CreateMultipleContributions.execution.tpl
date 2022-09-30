{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2021 SYSTOPIA                       |
| Author: J. Schuppe (schuppe@systopia.de)               |
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

<p>
  {ts domain='org.project60.banking'}This transaction was associated with these new contributions:{/ts}
  <ul>
    {foreach from=$contribution_ids item=contribution_id}
      {capture assign=contribution_link}{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}{/capture}
      <li><a href="{$contribution_link}">contribution #{$contribution_id}</a></li>
    {/foreach}
  </ul>
</p>
