Getting Started with CiviBanking
================================

Background
----------

CiviBanking imports financial data into CiviCRM.  The import files typically come as exports from another financial system such as a bank.  CiviBanking can reconcile bank statements to existing contributions in Civi (for example, online donations), or create new contributions.

In its current form, it is a toolkit requiring some work to put it together, but there is a lot of functionality close at hand that beats writing your own one-off import script.

These notes are not comprehensive, but they show the steps needed to import a file of contributions.

The project code is on https://github.com/Project60/org.project60.banking  The CiviCRM extension is installed from the /extension directory.

Overview
--------

CiviBanking uses a series of plugins to transform the raw data into CiviCRM actions.  Actions can be many things including changing the status of an existing contribution (reconciling), matching a membership or creating a contribution record.

The workflow consists of multiple steps to read and parse the data file and then determine what to do with that data.  

Plugins are of 3 types:
   * Import - reading a file and storing in the database
   * Match - the meat
   * Export - for outputs other than CiviCRM changes

You will need at least one import and one match plugin.  

The CiviBanking extension provides a set of configurable plugins of all types - and of course, you can write your own if you need to.

All the plugins are configured using JSON.  JSON is fairly simply to read but it's easy to make mistakes in the syntax.  You may find a syntax checker and formatter helpful such as https://jsonformatter.curiousconcept.com

Configuration examples are included in the /configuration_database directory on the github repo and provide many clues.

Import Plugins
--------------

The import plugins are:
   * Dummy Data Importer Plugin
   * Configurable CSV Importer
   * Configurable XML Importer

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
   * amount -
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

The end result of the importer plugin is a set of records ("bank transactions") parsed to  a collection of attributes used by the matcher plugins.

Match Plugins
-------------

Much of the work happens in these plugins.  The available plugins are:
   * Account Lookup Analyser
   * Batch Matcher
   * Contribution Matcher
   * Create Contribution Matcher Plugin
   * Default Options
   * Dummy Matcher Test Plugin
   * Generic Matcher Plugin
   * Ignore Matcher
   * Membership Matcher Plugin
   * RegEx Analyser
   * Recurring Contribution Matcher Plugin
   * SEPA Matcher

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

Note that 'd_email' it is not stored as an attribute.  The first of the actions copies the matched value (d_email) to the attribute 'matched_d_email'.  In this case, the email address is already available, but creating a new attribute helps with debugging since we can see what data the second action is working with.

The second action means: lookup a Contact record, returning the id by searching on the email attribute of the Civi contact record.  The email address to search for is "d_email", and if the lookup is successful, store the id in the contact_id_from_email attribute.

A site such as https://regex101.com is helpful for testing your regexes - and explaining someone else's!  Check that you use the PCRE flavour.

Note that the pattern should include the delimiters "/", or alternate delimiters such as "#" if you need to match / within your pattern. Check the '(?P<var>...)' description for named capturing groups here if that is not familiar:  http://www.regular-expressions.info/named.html

The actions often just need to copy the matched string (substring) into a new attribute.

The "lookup" action shown above calls the getSingle() call of the API which returns a value only if there is a unique match.  No value is returned if there are no matches or duplicate matches.  By using the API Explorer, you can check what parameters to add to the lookup call and also what results are returned for your data.

In our case, the import records contain a unique id for the contact that is stored in a custom field in CiviCRM.  By using the lookup action we map that back to the contact_id of the record.

The contact_id attribute is one of the "magic" or reserved ones.  When that is populated, it is understood to be the internal contact id of the CiviCRM contact that owns the transaction.  This will show in the GUI.

Available actions include:
   * calculate - run code
   * copy - as above
   * copy_append - append the value to an existing attribute
   * copy_ltrim_zeros - copy and remove leading zeros
   * lookup - as above
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

Note the ordering of the unintuitive ordering of the parameters!  This is understood as "copy the value of btx.fin_type_id to contribution.financial_type_id".

The amount, currency and date are automatically propagated to the contribution namespace.

Configuring Plugins
-------------------

As of this writing, there is no GUI for configuring plugins.  Configuration is done by SQL statements and hand-coding JSON config strings.

Start by installing the CiviCRM extension.  This is not available through the extensions GUI.  Download (or git clone) the code from the github repository and copy the 'extension' directory to Civi's extension directory and then enable it.

Fire up your SQL client of choice such as mysql or phpmyadmin and you should see some new tables in the civi database:
   * civicrm_bank_account
   * civicrm_bank_account_reference
   * civicrm_bank_plugin_instance
   * civicrm_bank_tx
   * civicrm_bank_tx_batch

civicrm_bank_plugin_instance is the one you will be dealing with most.  It determines which plugins are run, their ordering and their config.

In addition, new entries are made in the civicrm_option_value and civicrm_option_group tables.  You will need the values from these and these will be specific to your Civi instance.

The plugin types can be found with:
`select civicrm_option_value.id, civicrm_option_value.label
  from
    civicrm_option_value,
    civicrm_option_group
  where
    civicrm_option_group.name='civicrm_banking.plugin_classes'
  and civicrm_option_value.option_group_id=civicrm_option_group.id`

Output:
`+-----+---------------+  
| id  | label         |
+-----+---------------+
| 787 | Import plugin |
| 788 | Match plugin  |
| 789 | Export plugin |
+-----+---------------+`

The plugins themselves are found with:

`select civicrm_option_value.id, civicrm_option_value.label
  from
    civicrm_option_value,
    civicrm_option_group
  where
    civicrm_option_group.name='civicrm_banking.plugin_types'
    and civicrm_option_value.option_group_id=civicrm_option_group.id`

Output:
`+-----+---------------------------------------+
| id  | label                                 |
+-----+---------------------------------------+
| 766 | Dummy Data Importer Plugin            |
| 767 | Configurable CSV Importer             |
| 768 | Configurable XML Importer             |
| 769 | Generic Matcher Plugin                |
| 770 | Create Contribution Matcher Plugin    |
| 771 | Recurring Contribution Matcher Plugin |
| 772 | Membership Matcher Plugin             |
| 773 | Dummy Matcher Test Plugin             |
| 774 | Default Options Matcher               |
| 775 | Ignore Matcher                        |
| 776 | Contribution Matcher                  |
| 777 | Batch Matcher                         |
| 778 | SEPA Matcher                          |
| 779 | RegEx Analyser                        |
| 780 | Account Lookup Analyser               |
| 781 | Configurable CSV Exporter             |
+-----+---------------------------------------+`

Create an unconfigured CSV importer with the following:
`insert into civicrm_bank_plugin_instance
(plugin_type_id, plugin_class_id, name, description, enabled, weight, config, state)
values (787, 767, 'My importer', 'Import stuff', 1, 100, '{}', '{}')`

The values of 787 and 767 relate to the tables above.  Replace with values for your own system.

Similarly for a RegEx analyser:
`insert into civicrm_bank_plugin_instance
(plugin_type_id, plugin_class_id, name, description, enabled, weight, config, state)
values (788, 779, 'My RegEx matcher', 'Enrich the data', 1, 200, '{}', '{}')`

And the Create Contribution Matcher
`insert into civicrm_bank_plugin_instance
(plugin_type_id, plugin_class_id, name, description, enabled, weight, config, state)
values (788, 770, 'Create contribs', 'Create contribs', 1, 300, '{}', '{}')`

Use the weight parameter to control the order in which the plugins run.

Get the ids of these:
`select id, name from civicrm_bank_plugin_instance`
`+----+------------------+
| id | name             |
+----+------------------+
|  1 | My importer      |
|  2 | My RegEx matcher |
|  3 | Create contribs  |
+----+------------------+`

Then configure them by adding the JSON string:
`update civicrm_bank_plugin_instance set config='{ lots of lovely JSON }' where id = 1`
