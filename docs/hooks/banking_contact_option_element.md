# banking_contact_option_element

## Summary

This hook allows you to change the contact label used in the contact selection
drop-down on the "Review Bank Transaction" screen in CiviBanking.

## Availability

This hook was first available in CiviBanking 0.8.

## Definition

```javascript
banking_contact_option_element(event, label, contact)
```

## Parameters

-   @param event JavaScript event
-   @param label default label for this contact
-   @param contact contact data as fetched via the `Contact.get` CiviCRM API

## Example

This example changes the label to include the birth date in the contact label.

```javascript
// js/change_contact_label.js
CRM.$(document).on('banking_contact_option_element', function(event, label, contact) {
  label = label.split(']')[0] + '] ';
  // add birth_date if set
  if (contact.birth_date) {
    var birth_date = new Date(contact.birth_date);
    var options = { year: 'numeric', month: '2-digit', day: '2-digit' };
    var formatted_date = new Intl.DateTimeFormat('de-AT', options).format(birth_date);
    label += ' (' + formatted_date + ')';
  }
  // add address fields if set
  if (contact.street_address || contact.city || contact.postal_code) {
    label += ' (' + contact.street_address + ', ' + contact.postal_code +  ' ' + contact.city + ')';
  }
  return label;
});
```

```php
// bankingcustom.php
/**
 * Add JS to the banking review page
 *
 * @param $page
 *
 * @throws \Exception
 */
function bankingcustom_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Banking_Page_Review') {
    CRM_Core_Resources::singleton()->addScriptFile('com.example.bankingcustom', 'js/change_contact_label.js', 0, 'html-header');
  }
}
```
