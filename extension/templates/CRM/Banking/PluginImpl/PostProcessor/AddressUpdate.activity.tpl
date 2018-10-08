{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017-2018 SYSTOPIA                       |
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

<b>{ts domain='org.project60.banking'}The following address was received from a bank statment:{/ts}</b>
<table>
  <tr>
    <td>{ts domain='org.project60.banking'}Street Address{/ts}</td>
    <td>{$address_data.street_address}</td>
  </tr>
  {if $address_data.supplemental_address_1}
  <tr>
    <td>{ts domain='org.project60.banking'}Suppelemental Address 1{/ts}</td>
    <td>{$address_data.supplemental_address_1}</td>
  </tr>
  {/if}
  {if $address_data.supplemental_address_2}
  <tr>
    <td>{ts domain='org.project60.banking'}Suppelemental Address 2{/ts}</td>
    <td>{$address_data.supplemental_address_2}</td>
  </tr>
  {/if}
  <tr>
    <td>{ts domain='org.project60.banking'}Postal Code{/ts}</td>
    <td>{$address_data.postal_code}</td>
  </tr>
  <tr>
    <td>{ts domain='org.project60.banking'}City{/ts}</td>
    <td>{$address_data.city}</td>
  </tr>
  <tr>
    <td>{ts domain='org.project60.banking'}Country{/ts}</td>
    <td>{$address_data.country}</td>
  </tr>
</table>

<b>{ts domain='org.project60.banking'}Which differs from the existing one:{/ts}</b>
<table>
  <tr>
    <td>{ts domain='org.project60.banking'}Street Address{/ts}</td>
    <td>{$existing_address.street_address}</td>
  </tr>
  {if $existing_address.supplemental_address_1}
  <tr>
    <td>{ts domain='org.project60.banking'}Suppelemental Address 1{/ts}</td>
    <td>{$existing_address.supplemental_address_1}</td>
  </tr>
  {/if}
  {if $existing_address.supplemental_address_2}
  <tr>
    <td>{ts domain='org.project60.banking'}Suppelemental Address 2{/ts}</td>
    <td>{$existing_address.supplemental_address_2}</td>
  </tr>
  {/if}
  <tr>
    <td>{ts domain='org.project60.banking'}Postal Code{/ts}</td>
    <td>{$existing_address.postal_code}</td>
  </tr>
  <tr>
    <td>{ts domain='org.project60.banking'}City{/ts}</td>
    <td>{$existing_address.city}</td>
  </tr>
  <tr>
    <td>{ts domain='org.project60.banking'}Country{/ts}</td>
    <td>{$existing_address.country}</td>
  </tr>
</table>
