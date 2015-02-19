{assign var=contact_id value=$contact.id}
{assign var=membership_id value=$membership.id}
<div>
  <p><a title="{$contact.street_address}, {$contact.city}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name}</a> 
    has a <i>{$membership_status.label}</i> membership of type <a title="{$membership_type.description}" href="{crmURL p="civicrm/contact/view/membership" q="action=view&reset=1&cid=$contact_id&id=$membership_id&context=membership&selectedChild=member"}">"{$membership_type.name}"</a>
    since {$membership.start_date|crmDate:$config->dateformatFull}. {ts}If you confirm this suggestion, this payment will be recorded as a membership fee.{/ts}</p>
</div>
<div>
{if $last_fee.id}
  <table border="1">
    <tbody>
      <tr>
        <td>
          <div class="btxlabel">Last:</div>
          <div class="btxvalue">{$last_fee.total_amount|crmMoney:$last_fee.currency}</div>
        </td>
        <td>
          <div class="btxlabel">Paid:</div>
          <div class="btxvalue">{$last_fee.receive_date|crmDate:$config->dateformatFull} ({$last_fee.days} days ago)</div>
        </td>
        <td>
          <div class="btxlabel">Cycle:</div>
          <div class="btxvalue">{$membership_type.duration_interval}&nbsp;{$membership_type.duration_unit}</div>
        </td>
        <td>
          <div class="btxlabel">Type:</div>
          <div class="btxvalue">{$membership_type.period_type}</div>
        </td>
      </tr>
    </tbody>
  </table>
{else}
  <p>{ts}This would be the first fee paid for this membership.{/ts}
{/if}
</div>
