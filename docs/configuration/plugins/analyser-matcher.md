## Analysers

!!! note
    This section is yet to be completed.
    
    TODO: Describe generic configuration properties for required values, value
    propagation, automatic execution, etc.

### Regex Analyser Plugin

As its name suggests, this provides a regular expression engine to analyse
attribute values that were created by the import phase, e.g. the plugin can
check in the imported data if a field contains specific text, like _PAYPAL_ or
_paypal_ in the _purpose_ field, and set attributes in transaction data based on
its findings, e.g. the financial type could be set to a certain value.

The _analyser plugin_ will have configuration details to specify the regular
expression to evaluate, what fields to check,
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

### Account Lookup Analyser

!!! note
    This section is yet to be completed.

## Matchers

#### Default Options Matcher

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

Where the matching is being done on a unique ID, then we can be confident that
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

!!! note
    This section is yet to be completed.

#### Recurring Contribution Matcher

!!! note
    This section is yet to be completed.

#### SEPA Matcher Plugin

!!! note
    This section is yet to be completed.

#### Membership Matcher

!!! note
    This section is yet to be completed.

#### Ignore Matcher

!!! note
    This section is yet to be completed.

#### Batch Matcher

!!! note
    This section is yet to be completed.
