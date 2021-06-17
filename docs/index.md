CiviBanking is a CiviCRM extension for analysing and processing bank statements
or transaction reports exported from payment providers, such as PayPal, Mollie,
or Visa.

The extension can import transactions from these statements into a
CiviBanking-internal data structure and uses a series of plugins to transform
the raw data into CiviCRM actions. Actions can be many things including changing
the status of an existing contribution (reconciling), matching a membership or
creating a contribution record.

Since a bank account is crucial information for reconciling bank statement
transactions, CiviBanking provides a new entity for storing bank account
information on contact records in multiple formats, e.g. IBAN, national account
and bank numbers, or payment provider identifiers.

CiviBanking configuration is highly individual, quite a lot of scenarios can be
modeled with CiviBanking, and also be set up to process automatically. However,
configuring CiviBanking for a specific scenario is not an easy task and involves
writing JSON code.
