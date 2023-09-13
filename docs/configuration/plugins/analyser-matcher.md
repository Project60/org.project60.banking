## Analysers

!!! note
    This section is yet to be completed.
    
    TODO: Describe generic configuration properties for required values, value
    propagation, automatic execution, etc.
    
In the samples database you can find different samples for both analysers and matchers. 
Multiple analysers can be used to provide/set different types of information for the contributions. 
If you are for example running a larger amount of campaigns, or have multiple bank accounts, 
using different analysers to assign your contributions to campaign ids and bank accounts could be helpful.
	Analyser(s) and Matchers for existing contributions: As the name says it: 
the contributions exist in the database and the bank transactions imported will be assigned to an existing contribution.
When using such analysers/matchers, make sure you test logics specific to your organisation,
for example - partial payments or incorrect payment amounts. 

Analysers and Matchers for creating new contributions: Here the analysers must provide all necessary fields to create a new contribution. 
Your matchers would use the data provided by the analysers,
to create new contributions and assign the bank payment to them. Here different types of matchers/szenarios exist. 

`required values`: Based on whether creating a new contribution, or matching against existing contributions, the matchers have different required values.
All values are addressed in the matchers with `btx.field_name`. 

When matching to existing contributions, the contribution_id is the only required field. 
When creating  new contributions more values are required, as a minimum 
"btx.financial_type_id" and  "btx.payment_instrument_id".

`value_propagation`: This copies the values from the btx namespace to the contribution field. 
Important for storing information like source, campaign, payment instrument, 
note, or populating custom fields with data from the transaction.
 

If a required value is missing, the matcher will ignore the failing bank transaction.  
For details on further configuration variables , like `auto_exec`, `factor` or `treshhold` see the matchers section. 

Note, that you can re-analyse your transactions multiple times. 

You can view all analysed values in the `details` section of every transaction. 
Click to expand and examine all fieds/values, which the analyser has populated
with data. 


### Regex Analyser Plugin

As its name suggests, this provides a regular expression engine to analyse
attribute values that were created by the import phase, e.g. the plugin can
check in the imported data if a field contains specific text, like _PAYPAL_ or
_paypal_ in the _purpose_ field, and set attributes in transaction data based on
its findings, e.g. the financial type could be set to a certain value.

The _analyser plugin_ will have configuration details to specify the regular
expression to evaluate which fields to check,
and how to enrich transaction data with the correct values.

More information on Regular Expressions can be found in the
[Wikipedia](https://en.wikipedia.org/wiki/Regular_expression). A test site such
as [https://regex101.com](https://regex101.com) is helpful for testing your
regular expressions - and explaining someone else's!  Check that you use the
PCRE flavour.

The Regex Analyser configuration contains a list of rules. Each rule defines the
variable that it is examining and a regex pattern along with a set of actions to
take when it matches successfully.

For example:

```JSON
{
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
}
```

This looks at the `donor_e-mail` attribute (created during the import), and
matches it against the regex `"/^(?P<d_email>.+@.+)/"`. If that match is
successful, (anything containing `@` - a very rudimentary syntax check for email
addresses), the result is being stored temporarily in the `d_email` field.

Note that `d_email` is not automatically stored as an attribute. The first of
the actions copies the matched value `d_email` to the attribute
`matched_d_email`. In this case, the e-mail address is already available, but
creating a new attribute helps with debugging since we can see what data the
second action is working with.

The second action means: look up a Contact record, returning the ID by searching
on the `email` attribute of the CiviCRM contact record. The e-mail address to
search for is in `d_email`, and if the lookup is successful, store the ID in the
`contact_id_from_email` attribute.

Note that the pattern should include the delimiters `/`, or alternate delimiters
such as `#` if you need to match the character `/` within your pattern. Check
the `(?P<var>...)` description for named capturing groups, if that is not
familiar to you, [here](http://www.regular-expressions.info/named.html).

The actions often just need to copy the matched string (substring) into a new
attribute.

The `lookup` action shown above calls the `getSingle()` call of the API which
returns a value only if there is a unique match. No value is being returned if
there are no matches or duplicate matches. By using the API Explorer, you can
check what parameters to add to the lookup call and also what results are being
returned for your data.

In our case, the imported records contain a unique ID for the contact that is
stored in a custom field in CiviCRM. By using the lookup action we map that back
to the `contact_id` of the record.

The `contact_id` attribute is one of the "magic" or reserved ones. When that is
populated, it is being understood to be the internal contact ID of the CiviCRM
contact that initiated the transaction. This will also be shown in the user
interface.

Not veryfied: The `trxn_id` attribute is also important. When that is
populated, most likely from an external payment processor, it is being automatically matched to the contribution id.

Available actions include:

* `calculate` - runs a PHP expression (CAUTION!)
* `copy` - copies the attribute value to another attribute
* `copy_append` - appends the value to an existing attribute
* `copy_ltrim_zeros` - copies and removes leading zeros
* `lookup` - looks up a CiviCRM API entity based on given parameters
* `map` - maps from the matched value to a new value
* `set` - sets an attribute to a specified value
* `preg_replace` - substitutes part of the string using a regular expression
* `strtolower` - converts characters to lower case

Below is a sample analyser, which analyses a `custom` payment reference field, which contains two values:
contact_id and contribution_id. Then it looks up. 
Both are set after a `lookup` for the entity. Do not forget
to use the API Explorer to see the correct parameters to add to the call. 
    
```JSON
{
  "rules": [
    {
      "comment": "FIND contribution by custom payment reference field ("custom_111" in this example)",
      "fields": [
        "purpose"
      ],
      "pattern": "#(?P<matching_id>[0-9]{12})#",
      "actions": [
        {
          "action": "lookup:Contribution,id,custom_111",
          "from": "matching_id",
          "to": "contribution_id"
        },
        {
          "comment": "look up contact",
          "action": "lookup:Contribution,contact_id,id",
          "from": "contribution_id",
          "to": "contact_id"
        },
        {
          "comment": "Payment instrument in this example should always be Banktransfer",
          "action": "set",
          "to": "payment_instrument_id",
          "value": "5"
        },
        {
          "action": "set",
          "to": "identified_by",
          "value": "matching id:"
        },
        {
          "action": "unset",
          "to": "name_temp"
        },
        {
          "action": "copy_append",
          "from": "matching_id",
          "to": "identified_by"
        }
      ]
    }
  ]
}
```

* `fields` - analyses the purpose field, which has been populated by the importer (your payment reference import field must be matched as purpose)
* `pattern` - the RegEx pattern(s) to look for and creates variable(s), which will be modified and set in the analysing process. 
* all `action` elements, as well as their definition and possible usage are described in details below. 

You can use multiple variables, by looking for multiple patterns. Example for a more complex payment reference, with 3 variables:

`Payment Reference: 101X202X505`

`"pattern": "\/X(?P<contact_id>[\\s\\d]+)X(?P<promotion_code>[\\s\\d]+)X(?P<othervar>[\\s\\d]+)X\/i"`

This will find
Contact ID: 102
Promotion code: 202
Othervar: 505

Below is an example using a payment processor transaction id as lookup field: 
It provides the contact_id, contribution_id and trxn_id fields for your matcher
 
```JSON
            {
                "comment": "Analyzes Twingle Payment codes, with the twingle extension, configured so contribution status is set pending",
                "fields": [
                    "purpose"
                ],
                "pattern": "\/Sofort-(Spende|Donation)\\s[A-Za-z0-9\\s]{0,8}\\s(?P<twingle_trxn_id_matched>[A-Z0-9\\s]{7,8})\\s($|ANAM:.*|EREF:.*)\/",
                "actions": [
                    {
                        "comment": "strip whitespaces from twingle_trxn_id_matched",
                        "action": "preg_replace",
                        "search_pattern": "\/\\s\/",
                        "from": "twingle_trxn_id_matched",
                        "replace": "",
                        "to": "twingle_trxn_id"
                    },
                    {
                        "action": "lookup:Contribution,id,trxn_id",
                        "from": "twingle_trxn_id",
                        "to": "contribution_id"
                    },
                    {
                        "action": "lookup:Contribution,trxn_id,id",
                        "from": "contribution_id",
                        "to": "trxn_id"
                    },
                    {
                        "action": "lookup:Contribution,contact_id,id",
                        "from": "contribution_id",
                        "to": "contact_id"
                    }
                ]
            }
```

You can also see in this example how to use preg_replace 
for example to sanitise the input. 
Note, that you can set the same field multiple times, for example if 
you need to trim empty space from the beginning and end of a string. 

### Account Lookup Analyser

!!! note
    This section is yet to be completed.

## Matchers
When creating new matchers, make sure that they run in the exact order in which you
want the transaction to be evaluated. 

PLEASE REVIEW: When one matcher identifies/processes a transaction, all other matchers will 
not run on this transaction. 

Verify the structure of your json and when using samples from the database configuration
make sure your structure is correct and starts with 
```JSON

   "config": {
   ...
   }
   
```
You can always import one database configuration sample 
into your system and use it as a kickstart. 

Note, that once you have imported data into your system 
you should not delete any related configurations (importer, analyser, matcher). 
You should instead deactivate them. 


#### Default Options Matcher
This matcher provides the default processing options for manual reconciling: 
* A list of contacts, looked up by name, with the option to: enter existing contribution ID to be matched
or create a new contribution for the contact.
* Ignore the transaction at all, as it does not belong to CiviCRM

!!! note
    This section is yet to be completed.
    

#### Create Contribution Matcher

The purpose of this matcher is to populate a `contribution` namespace with the
values needed to create a contribution. Depending on the options selected, this
will either be automatically executed or presented to the user to select the
desired action.

Since the matchers are sometimes dealing with uncertain data, they make use of a
weighing system to propose the "best" matches to the user. For example, a bank
statement may include a payer's name but it is unlikely to match exactly with
that in CiviCRM. Small deviations should be regarded as more likely to be a
correct match than large ones.

Where the matching is being done on a unique ID, we can be confident that
the correct CiviCRM record is being selected. In this case, we do not need the
complexity of the probability system.

So far, attribute names have been mostly arbitrary and have been simple names:
`fundraiser_user_name`. In fact, all these live in the `btx`
("Bank Transaction") namespace and are referred to in these plugins as
`btx.fundraiser_user_name`.

The `contribution` namespace needs to be populated with values from `btx` but
here the names are the parameters of the CiviCRM API for the Contribution
entity. Again, the API Explorer helps determine what names to use.

Note that there is also a `ba` namespace for Bank Accounts, but we will not
address that further.

A simple create contribution matcher is:

```JSON
{
    "auto_exec": false,
    "factor": 1.0,
    "required_values": [
        "btx.financial_type_id",
        "btx.payment_instrument_id"
    ],
    "value_propagation": {
        "btx.fin_type_id": "contribution.financial_type_id",
        "btx.campaign_id": "contribution.campaign_id",
        "btx.payment_id": "contribution.payment_instrument_id"
    }
}
```

* `auto_exec` - determines when the matcher will automatically create the
  contribution. For example, a value of 0.8 would mean contributions are
  automatically being created if the suggestion exceeds the 80% confidence. If
  false, the process will never automatically create a transaction but instead
  suggest the action to the user for them to approve.

* `factor` - in the range `[0, 1.0]` and is used to fine tune the confidence
  values of suggestions produced by this plugin. If the probability weighting
  system is not required, leave this as `1.0`.

* `threshold` - optional parameter threshold below which suggestions will be
  discarded. For example, setting this to `0.7` would only create or propose to
  the user matches with at least 70% confidence.

* `required_values` - specifies that the `financial_type_id` and
  `payment_instrument_id` must have been defined previously in the `btx`
  namespace, otherwise the rest of this plugin is not used.

* `value_propagation` - this section copies the values from the `btx` namespace
  to the `contribution` one.

Note the unintuitive order of the parameters! This is understood as "copy the
value of `btx.financial_type_id` to `contribution.financial_type_id`" instead of
the usual "assign `contribution.financial_type_id` to `btx.financial_type_id`".

The amount, currency and date are automatically propagated to the contribution
namespace.

#### Contribution Matcher
The configuration database has different examples for the two use cases: 
* Create new contribution
* Match against existing contribution

A simple example for matching against existing contributionss: 
```JSON
    "config": {
        "auto_exec": 0.9,
        "threshold": 0.5,
        "accepted_contribution_states": [
            "Pending",
            "Partially paid"
        ],
        "required_values": [
            "btx.contribution_id"
        ],
        "value_propagation": {
            "btx.bank_name": "contribution.custom_101",
            "btx.purspose": "contribution.note"
        },
        "contribution_list": "contribution_id",
        "contribution_search": false
    },
    "state": []
```

What this matcher does: 
If the analyser has identified and provided a contribution_id (required_values),
and the contribution is with status pending or partially paid, 
then populate the values from the purpose field to the contribution note field
and the custom variable `bank_name` from the transaction under
a custom contribution field with ID  101.

NEEDS REVIEW: The matching happens inside the 
`"contribution_list": "contribution_id"` part. 

!!! note
    This section is yet to be completed.

#### Recurring Contribution Matcher

!!! note
    This section is yet to be completed.

#### SEPA Matcher Plugin
You can see example configurations in the configuration database.

!!! note
    This section is yet to be completed.

#### Membership Matcher
You can see example configurations in the configuration database.

!!! note
    This section is yet to be completed.

#### Ignore Matcher
With an ignore matcher you can identify transactions, which 
should be automatically ignored by Civi Banking, for example
if one bank statement/import file can contain outgoing payments, 
fees, internal financial transactions.
!!! note
    This section is yet to be completed.

#### Batch Matcher
You can see example configurations in the configuration database.
!!! note
    This section is yet to be completed.
