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

{ts domain='org.project60.banking'}The following address was received from a bank statment:{/ts}

{ts domain='org.project60.banking'}Street Address{/ts}: {$address_data.street_address}
{if $address_data.supplemental_address_1}{ts domain='org.project60.banking'}Supplemental Address 1{/ts}: {$address_data.supplemental_address_1}{/if}
{if $address_data.supplemental_address_2}{ts domain='org.project60.banking'}Supplemental Address 2{/ts}: {$address_data.supplemental_address_2}{/if}
{ts domain='org.project60.banking'}Postal Code{/ts}: {$address_data.postal_code}
{ts domain='org.project60.banking'}City{/ts}: {$address_data.city}
{ts domain='org.project60.banking'}Country{/ts}: {$address_data.country}


{ts domain='org.project60.banking'}Which differs from the existing one:{/ts}

{ts domain='org.project60.banking'}Street Address{/ts}: {$existing_address.street_address}
{if $existing_address.supplemental_address_1}{ts domain='org.project60.banking'}Supplemental Address 1{/ts}: {$existing_address.supplemental_address_1}{/if}
{if $existing_address.supplemental_address_2}{ts domain='org.project60.banking'}Supplemental Address 2{/ts}: {$existing_address.supplemental_address_2}{/if}
{ts domain='org.project60.banking'}Postal Code{/ts}: {$existing_address.postal_code}
{ts domain='org.project60.banking'}City{/ts}: {$existing_address.city}
{ts domain='org.project60.banking'}Country{/ts}: {$existing_address.country}
