Getting Started with CiviBanking
================================

by Aidan Saunders

Background
----------

CiviBanking imports financial data into CiviCRM.  The import files typically come as exports from another financial system such as a bank.  CiviBanking can reconcile bank statements to existing contributions in Civi (for example, online donations), or create new contributions.

In its current form, it is a toolkit requiring some work to put it together, but there is a lot of functionality close at hand that beats writing your own one-off import script.

These notes are not comprehensive, but they show the steps needed to import a file of contributions.

The project code is on https://github.com/Project60/org.project60.banking  The CiviCRM extension should be installed from a dedicated release (https://github.com/Project60/org.project60.banking/releases). The ``master`` version is bleeding edge and not always stable.

Overview
--------

CiviBanking uses a series of plugins to transform the raw data into CiviCRM actions.  Actions can be many things including changing the status of an existing contribution (reconciling), matching a membership or creating a contribution record.

The workflow consists of multiple steps to read and parse the data file and then determine what to do with that data.  

Plugins are of 3 types:
   * Import - reading a file and storing in the database
   * Match - the meat
   * Export - export the reconciliation data for processing in other systems

You will need at least one import and one match plugin.  

The CiviBanking extension provides a set of configurable plugins of all types - and of course, you can write your own if you need to.

All the plugins are configured using JSON.  JSON is fairly simply to read but it's easy to make mistakes in the syntax.  You may find a syntax checker and formatter helpful such as https://jsonformatter.curiousconcept.com

Configuration examples are included in the /configuration_database directory on the github repo and provide many clues.

A configuration UI along with an extensive documenation of the paramters of the standard matchers is planned and subject to funding.

Import Plugins
--------------

The import plugins are:
   * Configurable CSV Importer
   * Configurable XML Importer
   * Dummy Data Importer Plugin (testing purposes only, not maintained any more)

For the common case of CSV, the config specifies the things you would expect: the delimiter, the character set encoding and whether the first line is a header line. The main section of the config are the "rules" that specify how the fields are parsed.

Assuming our header line includes "...,First Name,..." then a config rule of  
`    {  
        "from": "First Name",  
        "to":  "firstname",  
        "type": "set"  
    }  
`
simply sets the "firstname" attribute to the value in the "First Name" column.

It needs a little more help to interpret dates correctly and uses strtotime and a time format - see http://php.net/manual/en/datetime.createfromformat.php

For example, this parses a date such as 31/12/2015 into a donation_date field.
`     {
        "from": "Donation Date",
        "to": "donation_date",
        "type": "strtotime:d/m/Y"
    }
`

Available action types are:
   * amount - try some basic formatting for amounts
   * append - used to create one attribute from multiple fields in the input
   * format - for parsing numbers in a specified format
   * set - copy value
   * strtotime - convert to date / time
   * trim - like 'set' but trims leading/trailing spaces

Rules can also contain an "if" clause to match conditionally.

For example:
`       "if": "matches:/^[0-9]{8}$/"`
This will only apply when the field being matched is 8 digits long.

The attribute names are arbitrary except these four that must be defined by the import plugin:
   * booking_date
   * value_date
   * amount
   * currency

There is also a set of *magic* attributes, that can be interpreted by the review screen. Thes can be set by the importer, or later on in the process using an "analyser" type matcher.
   * name - plain text name of the party
   * contact_id - the party CiviCRM contact (only if it can be uniquely identified)
   * purpose - a description of the transaction's purpose
   * _IBAN - our own IBAN (in case of bank transactions)
   * _party_IBAN - party IBAN (in case of bank transactions)

The end result of the importer plugin is a set of records ("bank transactions") parsed to  a collection of attributes used by the matcher plugins.


Match Plugins
-------------

There are two basic kinds of matchers. On the one hand, there are those that produce suggestions, that the user (or the system) can accept in order to reconcile the transaction in a certain way. On the other had, matcher might not produce suggestions at all, but rather just expand the knowlege on a certain transaction by setting more of the attributes mentioned above. These matcher are referred to as analysers.

Analyser Plugins
----------------
   * Account Lookup Analyser
   * RegEx Analyser

Matcher Plugins
---------------
Much of the work happens in these plugins.  The available plugins are:
   * Default Options - creates two default suggestions: manual reconciliation and ignore
   * Batch Matcher - matches to payment batches
   * Contribution Matcher - matches to existing contributions
   * Create Contribution Matcher - creates suggestions to create now contribtuions
   * Ignore Matcher - can be configured to automatically ignore certain transactions
   * Membership Matcher Plugin - records transactions as membership dues
   * Recurring Contribution Matcher Plugin - reccords transactions as installments of recurring contribtuions
   * SEPA Matcher - intgrates with the CiviSEPA extension

Depricated / Test Matchers
--------------------------
   * Dummy Matcher Test Plugin
   * Generic Matcher Plugin


Describing all of these would take this document way beyond a "Getting started" doc!  We will look at those relevant for importing a file of contributions.  We configure as many plugins as needed to turn the imported data into contribution records that can be added to CiviCRM.

### RegEx Analyser

As its name suggests, this provides a regular expression engine to analyse attribute values that were created by the import phase.

Similar to the CSV Importer, the RegEx Analyser config contains a list of rules.  Each rule defines the variable that it is examining and a regex pattern along with a set of actions to take when it matches successfully.

For example:
`    {
      "comment": "get donor contact_id from donor_e-mail ",
      "fields": [
        "donor_e-mail"
      ],
      "pattern": "/^(?P<d_email>.*@.*)/",
      "actions": [
        {
          "action": "copy",
          "from": "d_email",
          "to": "matched_d_email"
        },
        {
          "action": "lookup:Contact,id,email",
          "from": "d_email",
          "to": "contact_id_from_email"
        }
      ]
    }`

This looks at the donor_e-mail attribute (created during the import), and matches it against the regex `"/^(?P<d_email>.+@.+)/"`.  If that match is successful, (anything containing '@' - a very rudimentary syntax check for email addresses), the result is stored temporarily in d_email.

Note that 'd_email' it is not automatically stored as an attribute.  The first of the actions copies the matched value (d_email) to the attribute 'matched_d_email'.  In this case, the email address is already available, but creating a new attribute helps with debugging since we can see what data the second action is working with.

The second action means: lookup a Contact record, returning the id by searching on the email attribute of the Civi contact record.  The email address to search for is "d_email", and if the lookup is successful, store the id in the contact_id_from_email attribute.

A site such as https://regex101.com is helpful for testing your regexes - and explaining someone else's!  Check that you use the PCRE flavour.

Note that the pattern should include the delimiters "/", or alternate delimiters such as "#" if you need to match / within your pattern. Check the '(?P<var>...)' description for named capturing groups here if that is not familiar:  http://www.regular-expressions.info/named.html

The actions often just need to copy the matched string (substring) into a new attribute.

The "lookup" action shown above calls the getSingle() call of the API which returns a value only if there is a unique match.  No value is returned if there are no matches or duplicate matches.  By using the API Explorer, you can check what parameters to add to the lookup call and also what results are returned for your data.

In our case, the import records contain a unique id for the contact that is stored in a custom field in CiviCRM.  By using the lookup action we map that back to the contact_id of the record.

The contact_id attribute is one of the "magic" or reserved ones.  When that is populated, it is understood to be the internal contact id of the CiviCRM contact that owns the transaction.  This will show in the GUI.

Available actions include:
   * calculate - run PHP expression (CAUTION!)
   * copy - as above
   * copy_append - append the value to an existing attribute
   * copy_ltrim_zeros - copy and remove leading zeros
   * lookup - as described above
   * map - map from the matched value to a new value
   * set - sets an attribute to a specified value
   * preg_replace - substitute part of the string
   * strtolower - convert to lower case

### Create Contribution Matcher

The purpose of this matcher is to populate a "contribution" namespace with the values needed to create a contribution.  Depending on the options selected, this will either be automatically executed or presented to the user to select the desired action.

Since the matchers are sometimes dealing with uncertain data, they make use of a weighting system to propose the "best" matches to the user.  For example, a bank statement may include a payer's name but it is unlikely to match exactly with that in Civi.  Small deviations should be regarded as more likely to be a correct match than large ones.

Where the matching is being done on a unique id, then we can be confident that the correct Civi record is selected. In this case, we do not need the complexity of the probability system.

So far, attribute names have been mostly arbitrary and have been simple names: fundraiser_user_name.  In fact, all these live in the 'btx' namespace and are referred to in these plugins as 'btx.fundraiser_user_name'

The 'contribution' namespace needs to be populated with values from 'btx' but here the names are the parameters of the CiviCRM API for the Contribution entity.  Again, the API Explorer helps determine what names to use.

Note that there is also a 'ba' namespace for Bank Accounts, but we will not address that further.

A simple create contribution matcher is:
`{
    "auto_exec": false,
    "factor": 1.0,
    "required_values": [
        "btx.fin_type_id",
        "btx.payment_id",
    ],
    "value_propagation": {
        "btx.fin_type_id": "contribution.financial_type_id",
        "btx.campaign_id": "contribution.campaign_id",
        "btx.payment_id": "contribution.payment_instrument_id"
    }
}`

   * "auto_exec" - determines when the matcher will automatically create the contribution. For example, a value of 0.8 would mean contributions are automatically created if the suggestion exceeds the 80% confidence.  If false, the process will never automatically create a transaction but instead propose the action to the user for them to approve.

   * "factor" - in the range [0, 1.0] and is used to fine tune the confidence values of suggestions produced by this plugin.  If the probability weighting system is not required, leave this as 1.0

   * "threshold" - this optional parameter threshold below which suggestions will be discarded.  For example, setting this to 0.7 would only create or propose to the user matches with at least 70% confidence.

   * "required_values" - specifies that the "fin_type_id" and "payment_id" must have been defined previously in the btx namespace, otherwise the rest of this plugin is not used.

   * "value_propagation" - this section copies the values from the btx namespace to the contribution one.

Note the unintuitive order of the parameters!  This is understood as "copy the value of btx.fin_type_id to contribution.financial_type_id" instead of the usual "assign contribution.financial_type_id to btx.fin_type_id".

The amount, currency and date are automatically propagated to the contribution namespace.

Configuring Plugins
-------------------

There is a GUI for configuring plugins from **Banking > Configuration Manager**

You will need to configure at least one importer and some matchers. Look on this repository for examples under  configuration_database or configuration_database_legacy folders.

