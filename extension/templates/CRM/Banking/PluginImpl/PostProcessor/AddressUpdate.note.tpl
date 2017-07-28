{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2017 SYSTOPIA                            |
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

{ts}The following address was received from a bank statment:{/ts}

{ts}Street Address{/ts}: {$address_data.street_address}
{if $address_data.supplemental_address_1}{ts}Suppelemental Address 1{/ts}: {$address_data.supplemental_address_1}{/if}
{if $address_data.supplemental_address_2}{ts}Suppelemental Address 2{/ts}: {$address_data.supplemental_address_2}{/if}
{ts}Postal Code{/ts}: {$address_data.postal_code}
{ts}City{/ts}: {$address_data.city}
{ts}Country{/ts}: {$address_data.country}

{ts}Which differs from the existing one:{/ts}

{ts}Street Address{/ts}: {$existing_address.street_address}
{if $existing_address.supplemental_address_1}{ts}Suppelemental Address 1{/ts}: {$existing_address.supplemental_address_1}{/if}
{if $existing_address.supplemental_address_2}{ts}Suppelemental Address 2{/ts}: {$existing_address.supplemental_address_2}{/if}
{ts}Postal Code{/ts}: {$existing_address.postal_code}
{ts}City{/ts}: {$existing_address.city}
{ts}Country{/ts}: {$existing_address.country}
