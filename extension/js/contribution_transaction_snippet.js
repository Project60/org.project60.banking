/*-------------------------------------------------------+
| SYSTOPIA Remote Event Extension                        |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


cj(document).ready(function() {
  // inject stuff
  cj("tr.crm-event-participantview-form-block-event")
    .first()
    .after(`
      <tr class="crm-event-participantview-form-block-sessions">
        <td class="label">` + CRM.vars.remoteevent_participant_sessions.label + `</td>
        <td><a class="crm-popup" href="` + CRM.vars.remoteevent_participant_sessions.link + `" title="` + CRM.vars.remoteevent_participant_sessions.value_title + `">` + CRM.vars.remoteevent_participant_sessions.value_text + `</a></td>
      </tr>`);

  // apparently not needed: make sure we reload after a popup closes
  // cj(document).on('crmPopupFormSuccess', function () {
  //   // gray out existing form
  //   cj("div.remote-session-main-container").addClass("disabled");
  //
  //   // trigger reload (how to only reload the tab?)
  //   location.replace(CRM.vars.remoteevent.session_reload);
  // });
});