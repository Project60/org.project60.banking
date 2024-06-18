CiviBanking is a CiviCRM extension for analysing and processing bank statements
or transaction reports exported from payment providers, such as PayPal, Mollie,
or Visa.

The extension can import transactions from these statements into a
CiviBanking-internal data structure and uses a series of plugins to transform
the raw data into CiviCRM actions. Actions can be many things including changing
the status of an existing contribution (reconciling), matching a membership, matching a campaign creating a contribution record.

**CiviBanking is a powerful tool and and can save considerable booking work in everyday life.** End users should start reading the [user guide](user-guide.md) in order to be able to process bank statements.

On the other hand CiviBanking configuration is highly individual, quite a lot of scenarios can be modeled with CiviBanking, and also be set up to process automatically. CiviBanking is only able to greatly simplify the daily booking routine with a configuration of the plugins adapted to the individual workflow. Start [reading the description of plugins](configuration/plugins/concept.md) in order to adopt CiviBanking to your needs.

## Non technical and organisational recommendations

A strong recommendation before using CiviBanking is to thoroughly document and improve (financial) organisational processes. Therefore, a few best practices are mentioned here.

Set up multiple bank accounts for your organisation, for recording and identifying the source of the donation and payment. Make sure that these are limited to transactions you want to save in CiviCRM only. Avoid a mixture of incoming and outgoing payments or different types of payments without any interest in regard to your stakeholder. Even if you ignore these via the analyzer, you might end up with problems with the accountancy.

* Create a good overview of all possible payment flows so that you take all transactions into account. The sources where you collect statements should be disjoint.
* Handling duplicates should work well. It is a good idea to use the XCM extension. The default-profile should be able to match a contact by last name and IBAN at least
* Make sure that payments have unique IDs and you are able to identify it somehow.
* You should intelligently pre-fill your intended purposes for donors, and you should identify references from online payment service providers on the basis of bank statements.

These best practices make it much easier (for an assistance) to create a good individual configuration.

# 