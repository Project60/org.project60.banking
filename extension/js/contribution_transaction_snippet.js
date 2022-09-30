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
  // todo: find better selector for the row in the contribution view we want to be injected after
  let previous_row = cj("div.crm-contribution-view-form-block")
      .find("table.crm-info-panel")
      .first()
      .find("tr")
      .get(8);
  cj(previous_row)
      .after(`
        <tr>
          <td class="label crm-popup">` + CRM.vars.contribution_transactions.label + `</td>
          <td>` + CRM.vars.contribution_transactions.links + `</td>
        </tr>      
      `);
});