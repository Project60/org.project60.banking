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

{assign var=contact_id value=$contact.id}
{assign var=contribution_id value=$contribution.id}

{capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
{capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
{capture assign=contribution_url}{crmURL p="civicrm/contact/view/contribution" q="reset=1&action=update&context=contribution&id=$contribution_id&cid=$contact_id"}{/capture}

{if $error}
<div>
  {ts}An error has occurred:{/ts} {$error}<br/>
  {ts}This suggestion is possibly outdated. Please try and analyse this transaction again.{/ts}
</div>
{else}
<div>
  {ts}There seems to be a match:{/ts}
  {if $reasons}
  <ul>
  {foreach from=$reasons item=reason}
    <li>{$reason}</li>
  {/foreach}
  </ul>
  {/if}
  <div>
    <table border="1">
      <tbody>
        <tr>
          <td>
            <div class="btxlabel">{ts}Donor{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contact_link}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Amount{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.total_amount|crmMoney:$contribution.currency}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Date{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.receive_date|crmDate:$config->dateformatFull}</div>
          </td>
          <td>
            <div class="btxlabel">{ts}Type{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.financial_type}</div>
          </td>
          <td align='center'>
            <a href="{$contribution_url}" target="_blank">{ts}edit contribution{/ts}</td>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  {if not $cancellation_cancel_reason or $cancellation_cancel_fee}
  <div>
    {if $cancellation_cancel_reason}
    <label for="cancel_reason">{ts}Cancellation Reason{/ts}:</label>
    <input type="text" size="40" name="cancel_reason" value="{$cancel_reason}" />
    &nbsp;&nbsp;&nbsp;&nbsp;
    {/if}
    {if $cancellation_cancel_fee}
    <label for="cancel_fee">{ts}Cancellation Fee{/ts}:</label>
    <input type="text" style="text-align:right" size="5" name="cancel_fee" value="{$cancel_fee|string_format:'%.2f'}" />&nbsp;{$contribution.currency}
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
  </div>
  {/if}
</div>
{/if}