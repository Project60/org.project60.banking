## Extension Settings

The extension provides the following general settings:

### General Settings

- *Statement User Interface*    
  CiviBanking underwent a major user interface renovation. The traditional user
  interface is being kept for legacy users and not being documented.
- *CiviBanking Menu Position*
- *Configuration Editor Default Mode*
- *Log Level*
- *Log File*

### Bank Account Settings

- *Don't store bank accounts automatically*
- *Normalise bank account references*
- *Validate bank account references*
- *Probability of contact matching based on bank account*
- *Lenient bank account dedupe*


## Importing Statements

Once an importer plugin is being set up, bank statements can be imported using
the *Import statements* which is accessible in the CiviBanking menu.

First, select an importer plugin (e.g. an *CAMT.053 XML* or a *CSV* importer)
appropriate for your statement file, then select the file to upload and hit
*Import*.

The importer will process the file as configured in the importer plugin. The
results being shown are dependent on the importer plugin configuration. Usually,
each processed transaction will be logged with its result, either success or
error. At the bottom of the import result page, buttons are placed for importing
again, and for reviewing the imported statement.

## Viewing Statements

From the CiviBanking menu, the *Show Statements* menu entry will lead to an
overview of all imported statements, showing the following information:

- *Start Date* - Date of first transaction in the statement
- *End Date* - Date of last transaction in the statement
- *Account* - Bank account the statement was created for
- *Amount* - Total amount of transactions in the statement
- *Reference* - Identifier of the statements
- *Sequence* - Sequential number of the statement, if applicable
- *Transactions* - Number of transactions in the statement, itemized by their
  current status:
    - *New* - Transactions that have not been analysed after importing
    - *Analysed* - Transactions that have already been analysed after importing
    - *Processed* - Transactions that have been successfully processed
    - *Ignored* - Transactions that have been ignored, i.e. not processed

For each statement, the following operations can be selected:

- *Review Statement*
- *List Lines*
- *Delete Statement*

When selecting *List Lines*, all transactions in the statement are being listed
with their key information in a table view:

- *Date*
- *Amount*
- *Purpose*
- *Account holder*
- *Status* (see status list above)

For each transaction, the following operations can be selected:

- *Review Transaction*
- *Delete Transaction*

## Reviewing Statements

When selecting to either review the complete statement or a single transaction,
the CiviBanking review screen will be loaded for analysing and processing the
statement's transactions one by one. The view is subdivided into the following
sections:

- *Basic Information* - Shows information directly extracted from the imported
  statement for each transaction, such as the amount, the booking and value
  date
- *Transaction Information* - Shows information about the (internal) transaction
  object: the statement and transaction number andits current processing state
- *Debitor Information* - Shows infomration about the holder of the account the
  transaction is originating from, including their account reference, name, and
  address - given that data has been imported from the statement
- *Details* - Shows (mostly) internal information that CiviBanking has extracted
  from the transaction, either during import or analysis. This section's content
  is dependent on the configured plugins and holds all data fields being stored
  with the transaction. Usually, this section is not relevant to the end user,
  and is thus being collapsed by default.

Below the transaction information, buttons are placed for navigating between
transactions in the current statement, and for analysing and processing them.

The review process is divided into the following status:

1. *New* - This is the initial status for each transaction, after it has been
   imported. The status indicated, that no analysis has been done yet and that
   it can be processed according to the plugin configuration.
2. *Analysed* - This is the status for transactions that have (at least once)
   been analysed, but not yet processed. Transactions within this state can be
   re-analysed again or processed according to the plugin configuration.
3. *Processed* or *Ignored* -  This status indicates that the transaction has
   been successfully processed or ignored as not being relevant, according to
   the plugin configuration.

Transactions in the *Analysed* state will be provided with suggestions as to how
they can be processed. The suggestions are dependent on the plugin
configuration. Usually, there will be at least a suggestion to enter the
transaction manually as contributions for a contact, as well as a suggestion to
ignore the transaction (when it should not be entered as a CiviCRM contribution
record). Match plugins can provide additional suggestions for the end user to
simply select one without having to further set contribution fields.

Generating suggestions depends on CiviBanking being able to identify a CiviCRM
contact for the account holder. This will be the case when either a contact has
the bank account set as a Bank Account entity, or when a plugin is configured to
try to identify a contact by the account holder name. This may yield either a
distinct result, or mutliple contacts (when there are similar names or
duplicated), or no contact at all (when the contact exists with a differing or
without a name, or does not exist). Changes to contacts or newly created ones
will be considered when re- analysing the transaction.

Each suggestion is assigned a precentage threshold for indicating how confident
CiviBanking was in suggesting what to do with the transaction. This is
configurable within each match plugin and should be based on empiricial values,
enabling the end user to trust these thresholds and be able to just select the
most reliable suggestion for processing the transaction.

### Examples

Given a transaction being a donation for a specific campaign, CiviBanking could
be configured to suggest entering it as a contribution of the type "Donation",
associated with the CiviCRM campaign and adding some information in the *Source*
field for the contribution.

Given a transaction being a membership fee payment, CiviBanking could be
configured to suggest entering it as a contribution of the type
"Membership Fee (Reduced)" or "Membership Fee (Regular)", depending on the
amount, and extending the membership afterwards.

Given a transaction being a SEPA Direct Debit payment, CiviBanking could be
configured to identify an existing contribution with the status "Pending",
created for the SEPA mandate reference in the transaction purpose, and
suggesting to set this contribution to "Completed" to mark the SEPA payment as
successful. Alternatively, for return debits for failed SEPA debits, CiviBanking
could be configured to suggest to be process it by setting the contribution
status to "Cancelled" and adding a cancel reason given in the transaction
purpose.

## Processing Statements

Once a suggestion has been selected, hitting *Confirm* will process it  with the
plugin configuration that provided the suggestion. This is dependent on the
plugin type and configuration, but will usually create or update a CiviCRM
contribution for the identified contact.

When there are post processor plugins set up in the plugin configuration,
additional actions might be performed with the contribution afterwards, e.g.
extending a corresponding membership, updating the contact's address, or setting
the contact's deceased status.

Each transaction that has been processed (or ignored) will be displayed with an
information text on what has been done with it. This is dependent on the type
and configuration of the plugin that was responsible for processing, but will
usually display a link to the contribution(s) and the contact, and denote which
contact/user processed it.

Match plugins can be configured to process transactions automatically, when
certain conditions are met, e.g. a threshold of a specific percentage, or a
campaign ID being present in the transaction purpose, etc. This is useful for
regular transactions that can certainly be related to a specific scenario, e.g.
SEPA Direct Debit payments, that do not need manual analysis. When a suggestion
is being created with the option to be automatically executed, the transaction
is being processed without showing the analysis screen to the end user, i.e.
when selecting *Review Statement* or *Review Transaction* on the overview pages,
only transactions that can not be processed automatically will be shown for
manual reconciliation.
