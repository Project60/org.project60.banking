# hook_civicrm_banking_transaction_summary

## Summary

This hook allows you to change the transaction summary blocks displayed on top
of the "Review Bank Transaction" screen in CiviBanking.

## Availability

This hook was first available in CiviBanking 0.8.

## Definition

```php
hook_civicrm_banking_transaction_summary($banking_transaction, &$summary_blocks)
```

## Parameters

-   @param CRM_Banking_BAO_BankTransaction $banking_transaction
-   @param array $summary_blocks

## Example

This example changes the ReviewDebtor block to show the contact's birth date.

```php
// bankingcustom.php
/**
 * Replace (some of) the summary blocks on the banking review page
 *
 * @param CRM_Banking_BAO_BankTransaction $banking_transaction
 * @param array $summary_blocks
 */
function bankingcustom_civicrm_banking_transaction_summary($banking_transaction, &$summary_blocks) {
  $summary = new CRM_Bankingcustom_TransactionSummary($banking_transaction, $summary_blocks);
  $summary->modify();
}

// CRM/Bankingcustom/TransactionSummary.php
class CRM_Bankingcustom_TransactionSummary {

  /**
   * @var CRM_Banking_BAO_BankTransaction
   */
  private $transaction;

  /**
   * @var array
   */
  private $blocks;

  public function __construct($transaction, &$blocks) {
    $this->transaction = $transaction;
    $this->blocks = &$blocks;
  }

  /**
   * Fetch the birth_date of the contact matching this transaction
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function modify() {
    $template = CRM_Core_Smarty::singleton();
    if (!empty($this->transaction->party_ba_id)) {
      // fetch the contact associated with the BankingAccount matching this trxn
      $result = reset(civicrm_api3('BankingAccount', 'get', [
        'id'                   => $this->transaction->party_ba_id,
        'return'               => ['contact_id'],
        'api.Contact.getvalue' => ['return' => 'birth_date'],
      ])['values']);
      if (!empty($result['api.Contact.getvalue'])) {
        // make $birth_date available to the template
        $template->assign('birth_date', $result['api.Contact.getvalue']);
      }
    }
    // replace the "ReviewDebtor" block with a custom template
    $this->blocks['ReviewDebtor'] = $template->fetch(
      'CRM/Bankingcustom/TransactionSummary/ReviewDebtor.tpl'
    );
  }

}

// templates/CRM/Bankingcustom/TransactionSummary/ReviewDebtor.tpl
<table id="btx-debtor" style="width: 50%">
  <tr>
    <td>
      <div class="btxheader">
        {ts domain='org.project60.banking'}DEBTOR INFO{/ts}
      </div>
    </td>
  </tr>
  <tr >
    <td>
      <div class="btxlabel">{ts domain='org.project60.banking'}Account{/ts}</div>
      <div class="btxvalue btxl">
        {if $party_ba_references.0}
          {assign var=ba_contact_id value=$party_ba_references.0.contact_id}
          {if !$party_ba_references.0.contact_ok}<strike>{/if}
          <a title="{$party_ba_references.0.reference_type_label}">{$party_ba_references.0.reference}</a>
          <a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$ba_contact_id"}">[{$ba_contact_id}]</a>
          {if !$party_ba_references.0.contact_ok}</strike>{/if}
        {elseif $party_account_ref}
          <span title="{$party_account_reftypename}" class="notfound">{$party_account_ref} ({$party_account_reftype2})</span>
        {else}
          &nbsp;
        {/if}

      </div>
      <div class="btxlabel">{ts domain='org.project60.banking'}Address{/ts}</div>
      <div class="btxvalue btxl">
        {$payment_data_parsed.street_address}&nbsp;
      </div>
      <div class="btxlabel">&nbsp;</div>
      <div class="btxvalue btxl">
        {$payment_data_parsed.postal_code} {$payment_data_parsed.city}&nbsp;
      </div>
      <div class="btxlabel">{ts domain='org.project60.banking'}Owner{/ts}</div>
      <div class="btxvalue btxl">
        {$payment_data_parsed.name}{if $payment_data_parsed.email}&nbsp;({$payment_data_parsed.email}){/if}
      </div>
      {if $contact}
        <div class="btxlabel">{ts domain='org.project60.banking'}Contact{/ts}</div>
        <div class="btxvalue btxl">
          <a href="{$base_url}/civicrm/contact/view?reset=1&cid={$contact.id}">{$contact.display_name}{if $birth_date} ({$birth_date}){/if} [{$contact.id}]</a>
        </div>
      {/if}
    </td>
  </tr>
</table>
```
