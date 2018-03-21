{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{* this panel should display the matched rules (if any)
   AND offer to create a new one *}

{* this is just an example *}
<div class="rules-analyser-list">
{if $rules}
  <p>
    Rules Matched:
    <ul>
    {foreach from=$rules item=rule}
      <li>Rule [{$rule.id}] "{$rule->name}" matched.</li>
    {/foreach}
    </ul>
  </p>
{else}
  <p>No <em>rules</em> currently match this transaction.</p>
{/if}
</div>

<div class="rules-analyser-new">
<a class="button" onclick="alert("TODO")><span><div class="icon ui-icon-plus"></div>{ts}create new rule{/ts}</span></a>
</div>
