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

{capture assign=contribution_link}{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view&context=membership&selectedChild=contribute"}{/capture}
{capture assign=membership_link}{crmURL p="civicrm/contact/view/membership" q="action=view&reset=1&cid=$contact_id&id=$membership_id&context=membership&selectedChild=member"}{/capture}

<p>
  {ts 1=$contribution_link 2=$contribution_id 3=$membership_link 4=$membership_id domain='org.project60.banking'}This transaction was recorded as <a href="%1">membership fee contribution [%2]</a> for <a href="%3">membership [%4]</a>.{/ts}
</p>