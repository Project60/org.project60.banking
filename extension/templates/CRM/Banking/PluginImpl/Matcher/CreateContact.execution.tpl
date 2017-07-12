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

{capture assign=contribution_link}{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view&context=membership&selectedChild=contribute"}{/capture}
{capture assign=contact_link}{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}{/capture}

<p>
  {ts 1=$contribution_link 2=$contribution_id 3=$contact_link 4=$contact_id}This transaction was associated with the new <a href="%1">contribution #%2</a> on new contact <a href="%3">#%4</a>.{/ts}
</p>