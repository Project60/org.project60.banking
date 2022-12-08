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

{assign var=contribution_id value=$contribution.id}

{if $error}
<div>
  {ts domain='org.project60.banking'}An error has occurred:{/ts} {$error}<br/>
  {ts domain='org.project60.banking'}This suggestion is possibly outdated. Please try and analyse this transaction again.{/ts}
</div>
{else}
<div>
  <p>
    {ts 1=$contact_html domain='org.project60.banking'}This transaction is a SEPA direct debit contribution by %1.{/ts}
    {ts 1=$mandate_link 2=$mandate_reference domain='org.project60.banking'}The mandate reference is <a href="%1" target="_blank">%2</a>{/ts}
  </p>

  {if $cancellation_mode}
  <p>
    {if $cancellation_cancel_reason}
    <label for="cancel_reason">{ts domain='org.project60.banking'}Cancellation Reason{/ts}:</label>
    <input type="text" size="40" name="cancel_reason" value="{$cancel_reason}" {if not $cancel_reason_edit}disabled{/if} />
    &nbsp;&nbsp;&nbsp;&nbsp;
    {/if}
    {if $cancellation_cancel_fee}
    <label for="cancel_fee">{ts domain='org.project60.banking'}Cancellation Fee{/ts}:</label>
    <input type="text" style="text-align:right" size="5" name="cancel_fee" value="{$cancel_fee|string_format:'%.2f'}" {if not $cancel_fee_edit}disabled{/if} />&nbsp;{$contribution.currency}
    <script type="text/javascript">
    // Add JS function to mark invalid user input (adapted from: http://jqueryexamples4u.blogspot.de/2013/09/validate-input-field-allows-only-float.html)
    {literal}
    cj(function() {
      cj('[name="cancel_fee"]').keyup(function(e) {
        var entered_value = cj(this).val();
        var regexPattern = /^\d{0,8}(\.\d{1,2})?$/;         
        if(regexPattern.test(entered_value)) {
          cj(this).css('background-color', 'white');
        } else {
          cj(this).css('background-color', 'red');
        }
      });
    });
    {/literal}
    </script>
    {/if}
  </p>
  {/if}

  <p>
    {if not $cancellation_mode}
      {ts 1=$contribution_link 2=$contribution_id domain='org.project60.banking'}Contribution <a href="%1" target="_blank">[%2]</a> will be closed, as will be the sepa transaction group if this is the last contribution.{/ts}
    {else}
      {ts 1=$contribution_link 2=$contribution_id domain='org.project60.banking'}Contribution <a href="%1" target="_blank">[%2]</a> will be cancelled.{/ts}
      {if $cancel_mandate}
        {ts domain='org.project60.banking'}The mandate will also be cancelled, its status will change to INVALID.{/ts}
      {/if}
      {if $create_activity}
        {ts domain='org.project60.banking'}An activity will be created to trigger a manual follow-up.{/ts}
      {/if}
    {/if}
  </p>


</div>

{if $warnings}
<div>
  {ts domain='org.project60.banking'}<b>Warning! There are some problems with this contribution:</b>{/ts}
  <ul>
  {foreach from=$warnings item=warning}
    <li>{$warning}</li>
  {/foreach}
  </ul>
</div>
{/if}

{/if}
