Analysers & Matchers
--------------------

> [!NOTE]
> This section is yet to be completed.
>
> TODO: Describe generic configuration properties for required values, value
> propagation, automatic execution, etc.

# Table of Contents

* [How to use plugins from the configuration database](#how-to-use-plugins-from-the-configuration-database)
* [Analysers](#analysers)
  * [Regex Analyser Plugin](#regex-analyser-plugin)
    * [Regex Analyser Actions](#regex-analyser-actions)
    * [Configuration Examples](#configuration-examples)
  * [Account Lookup Analyser](#account-lookup-analyser) _- to be completed_
* [Matchers](#matchers)
  * [Default Options Matcher](#default-options-matcher)
  * [Create Contribution Matcher](#create-contribution-matcher)
  * [Contributions Matcher](#contribution-matcher)
    * [Configuration Parameters](#configuration-parameters)
    * [Contribution Matcher Example](#contribution-matcher-example)
  * [Recurring Contribution Matcher](#recurring-contribution-matcher) _- to be completed_
  * [SEPA Matcher Plugin](#sepa-matcher-plugin) _- to be completed_
  * [Membership Matcher](#membership-matcher) _- to be completed_
  * [Ignore Matcher](#ignore-matcher)
    * [Ignore Matcher Example](#ignore-matcher-example)
  * [Batch Matcher](#batch-matcher) _- to be completed_

# How to use plugins from the configuration database

In the [configuration database](https://github.com/Project60/org.project60.banking/tree/master/configuration_database)
you can find different samples for both analysers and matchers. Multiple
analysers can be used to provide/set different types of information for the
contributions. If you are for example running a larger amount of campaigns, or
have multiple bank accounts, using different analysers to assign your
contributions to campaign ids and bank accounts could be helpful.

When importing a plugin from the configuration database, make sure that the JSON
contains the entire plugin data and not just the configuration part that you
would normally paste into the code editor in the plugin settings:

```JSON
{
  "plugin_type_name": "match",
  "plugin_class_name": "matcher_default",
  "name": "Default Options",
  "description": "Provides the user with some default processing options.",
  "weight": "160",
  "config": {
    "auto_exec": false,
    "manual_enabled": true,
    ...
  },
  ...
}
```

You can always import a database configuration sample into your system and use
it as a kickstart.

> [!WARNING]
> Once you used a plugin to imported data into your system you should
> not delete the plugin, because processed transactions store the IDs of the
> applied plugins in their database record. Instead, you can disable the plugin.

# Analysers

Analysers are used to analyse the data provided by the importer and to set
attributes in the transaction data. The analysers are executed in the order
defined in the configuration. The order is important, because the analysers
can overwrite each other's results.

## Regex Analyser Plugin

As its name suggests, this plugin provides a regular expression engine to
analyse attribute values that were created during the import phase, e.g. the
plugin can check in the imported data if a field contains specific text, like
_PAYPAL_ or _paypal_ in the _purpose_ field, and set attributes in transaction
data based on its findings, e.g. the financial type could be set to a certain
value.

The analyser plugin defines in its configuration a set of `rules`. Each rule in
turn defines a set of `fields` to be analysed, a regex `pattern` to be matched
against the `fields`, and a set of `actions` to be executed if the pattern
matches.

More information on Regular Expressions can be found in
the [Wikipedia](https://en.wikipedia.org/wiki/Regular_expression). A test site
such as [https://regex101.com](https://regex101.com) is helpful for testing your
regular expressions - and explaining someone else's! Check that you use the PCRE
flavour.

### Regex Analyser Actions

* [`align_date`](#align_date-action) - aligns a date forwards or backwards
* [`api`](#api-action) - use any CiviCRM API3
* [`calculate`](#calculate-action) - runs a PHP expression (CAUTION!)
* [`copy`](#copy-action) - copies a field value
* [`copy_append`](#copy_append-action) - appends the value to an existing field
* [`copy_ltrim_zeros`](#copy_ltrim_zeros-action) - removes leading zeros
* [`lookup`](#lookup-action) - look up a value via `getsingle` APIv3 action
* [`map`](#map-action) - maps from the matched value to a new value
* [`preg_replace`](#preg_replace-action) - substitutes part of the string using a regular expression
* [`set`](#set-action) - sets an attribute to a specified value
* [`sha1`](#sha1-action) - reduces a field to SHA1 checksum
* [`sprint`](#sprint-action) - formats a string using `sprintf`
* [`strtolower`](#strtolower-action) - converts characters to lower case
* [`unset`](#unset-action) - unsets a certain value

#### `align_date` Action

The `align_date` action aligns a date forwards or backwards.

Assuming you want to find the next working day after the booking date, you could
create a rule that matches the date part of `booking_date` and adds one day.
This is done by defining `"skip_one"` in the `"skip"` parameter. In the case
that the next day falls on a weekend, you can also put `"weekend"` in
the `"skip"` array. This increases the date by the time span defined
in `"offset"` until the weekend is over.

##### Parameters

* `"skip"` - an array of strings that define the days to skip. Possible values
  are:
    * `"skip_one"`
    * `"weekend"`
    * a regex expression to match a date
* `"offset` - a string compatible to
  the [`strtotime()` function](https://www.php.net/manual/de/function.strtotime.php)
* `"from"` - the field name to read the date from
* `"to"` - the field name to write the date to

> [!IMPORTANT]
> Note that the `"skip"` and `"offset"` parameters must be defined within
> the `"rule"` body not within the `"action"`.

##### Example

Find the next working day and skip weekends:

```json
{
  "rules": [
    {
      "comment": "Next working day",
      "fields": [
        "booking_date"
      ],
      "pattern": "#^(?P<date>\\d{4}-\\d{2}-\\d{2})\\s\\d{2}:\\d{2}:\\d{2}$#",
      "skip": [
        "skip_one",
        "weekend"
      ],
      "offset": "+1 days",
      "actions": [
        {
          "comment": "Find next working day",
          "action": "align_date",
          "from": "date",
          "to": "next_working_day"
        }
      ]
    }
  ]
}
```

Skip the 1st of April as `receive_date`:

```json
{
  "rules": [
    {
      "comment": "Skip 1st of April",
      "fields": [
        "booking_date"
      ],
      "pattern": "#^(?P<date>\\d{4}-\\d{2}-\\d{2})\\s\\d{2}:\\d{2}:\\d{2}$#",
      "skip": [
        "#.*04-01-.*#"
      ],
      "offset": "+1 days",
      "actions": [
        {
          "comment": "Skip 1st of April",
          "action": "align_date",
          "from": "date",
          "to": "btx.receive_date"
        }
      ]
    }
  ]
}
```

#### `api` Action

The `api` Action allows you to call any CiviCRM API version 3 with any action 
and set the result(s) to one or more fields in the transaction data. 

In contrast to the `lookup` action, the `api` action can also call actions 
other than `getsingle`. Therefor it is possible to retrieve multiple results,
which can be set to a single field as a comma separated list (see the 
`multiple` parameter in the next section).

##### Parameters

* `"action"` - the CiviCRM API 3 call (schema: `api:<entity>:<action>:<result_field>[:multiple]`) containing:
  * `<entity>` - the CiviCRM API 3 entity to call
  * `<action>` - the CiviCRM API 3 action to call
  * `<result_field>` - the field of the result data to use
  * `"multiple"` - if set, the result will be a comma separated list of
    all results, otherwise the result will only be set if exactly one result is
    found
* `"to"` - the field name to write the result to
* `"const_<parameter_name>"` - any number of constant parameters to be passed 
  to the API call (see the API Explorer for the correct parameter names)
* `"param_<parameter_name>"` - any number of parameters to be passed to the API 
  call (see the API Explorer for the correct parameter names)

##### Example

```json
{
  "rules": [
    {
      "comment": "Find donations in honor of the recently deceased John Doe",
      "fields": [
        "purpose"
      ],
      "pattern": "/(memory|honor)\\s.*?John Doe/",
      "actions": [
        {
          "comment": "Create activity for donor",
          "action": "api:Activity:create:id",
          "to": "btx.activity_id",
          "const_activity_type_id": "Memorial",
          "const_subject": "In memory of John Doe",
          "param_activity_date_time": "btx.booking_date",
          "param_details": "btx.purpose",
          "param_target_id": "btx.contact_id",
          "const_source_contact_id": "42"
        }
      ]
    }
  ]
}
```
To be fair, this example is a bit contrived, but it is intended to demonstrate 
how to use the `api` action for actions other than `getsingle`.

In this example we assume that the donor was already identified by a previous
analyser and the `contact_id` was set to `btx.contact_id`. The analyser now
searches for the words `memory` or `honor` followed by `John Doe` in the
`purpose` field. If the pattern matches, the analyser creates an activity of
type `Memorial` for the donor. The return value of the API call is the ID of 
the newly created activity, which is set to the `btx.activity_id` field but 
isn't used any further.

#### `calculate` Action

The `calculate` action allows you to run a PHP expression on the transaction
data. This can be useful if you want to calculate a checksum or a hash value
based on other fields in the transaction data. As this action allows you to run
any PHP code, you should be very careful when using it. Always remember: With
great power comes great responsibility!

##### Parameters

* `"to"` - the field name to write the result to
* `"from"` - the PHP expression to run 
  * Schema `"(((int) \"{checksum_matched}\") % 97) == (((int) \"{checksum}\") % 
    97)"` (The value of the `"to"` field is `true` or `false`, depending on 
    whether the checksum is correct.)

##### Example

See the [Regex Analyser Example 3](#regex-analyser-example-3) for an example.

#### `copy` Action

The `copy` action copies a field value to another field. This is especially
useful to copy the result of a regex match to another field in the transaction
data (`btx` namespace).

##### Parameters

* `"from"` - the field name to copy the value from
* `"to"` - the field name to write the value to

##### Example

See the [Regex Analyser Example 1](#regex-analyser-example-1) for an example.

#### `copy_append` Action

The `copy_append` action appends a field value to another field. This can be 
used to concatenate multiple fields into one field, e.g. to create a checksum
or to prefix a field with a certain string.

##### Parameters

* `"from"` - the field name to copy the value from
* `"to"` - the field name to append the value to

##### Example

See the [Regex Analyser Example 3](#regex-analyser-example-3) for an example.

#### `copy_ltrim_zeros` Action

The `copy_ltrim_zeros` action copies a field value to another field and removes
leading zeros. This can be used to remove leading zeros from a field value, 
e.g. the contributors bank account number.

##### Parameters

* `"from"` - the field name to copy the value from
* `"to"` - the field name to write the trimmed value to

##### Example

```json
{
  "rules": [
    {
      "comment": "Trim leading zeros from bank account number",
      "fields": [
        "_ba_id"
      ],
      "pattern": "#^0\\d{9}#",
      "actions": [
        {
          "comment": "Trim leading zeros from bank account number",
          "action": "copy_ltrim_zeros",
          "from": "bank_account_number",
          "to": "bank_account_number"
        }
      ]
    }
  ]
}
```

In this example, the analyser searches for a bank account number starting with
a zero and followed by 9 digits. If the pattern matches, the analyser copies
the value of the `bank_account_number` field to the `bank_account_number` field
and removes the leading zeros.

#### `lookup` Action

The `lookup` action allows you to look up a CiviCRM API entity based on a given
parameter. The action can be used to look up any CiviCRM APIv3 entity, but it 
is especially useful to look up contacts, contributions, and campaigns.

In contrast to the `api` action, the `lookup` action can only call the
`getsingle` action of a CiviCRM APIv3. Therefor it is only possible to retrieve 
one result, which is set to the specified field in the transaction data.
Furthermore, the `getsingle` action will not allow you to perform any actions
that allow you to create or update data.

##### Parameters

* `"from"` - the field name to read the value from
* `"to"` - the field name to write the result to
* `"action"` - the CiviCRM API 3 call
  (schema: `lookup:<entity>,<result_field>,<lookup_field>`) containing:
  * `<entity>` - the CiviCRM API 3 entity to call
  * `<result_field>` - the field of the result data to use
  * `<lookup_field>` - the field to search for

##### Example

See the [Regex Analyser Example 1](#regex-analyser-example-1) for an example.

#### `map` Action

The `map` action maps a value to another value. This can be used, for example, 
to determine the financial type or the campaign of a donation based on the IBAN 
of the bank account where the contribution was received.

##### Parameters

* `"from"` - the field name to read the value from
* `"to"` - the field name to write the result to
* `"mapping` - a list of values to map from and to
  * Schema: `"map: {<from_value_1>: <to_value_1>, ...}"`

##### Example

```json
{
  "rules": [
    {
      "comment": "Map financial type based on IBAN",
      "fields": [
        "_IBAN"
      ],
      "pattern": "#^(?P<iban_matched>DE\\d{20})$#",
      "actions": [
        {
          "comment": "Map financial type based on IBAN",
          "action": "map",
          "from": "iban_matched",
          "to": "btx.financial_type_id",
          "mapping": {
            "DE12345678901234567890": 1,
            "DE09876543210987654321": 2
          }
        }
      ]
    }
  ]
}
```

In this example, the analyser searches for an IBAN starting with `DE` and
followed by 20 digits. If the pattern matches, the analyser maps the value of
the `iban_matched` corresponding to the in the `"mapping"` parameter defined
values to the `btx.financial_type_id` field.

#### `preg_replace` Action

The `preg_replace` action substitutes part of the string using a regular
expression. This can be used, for example, to remove whitespaces from a field
value.

##### Parameters

* `"from"` - the field name to read the value from
* `"to"` - the field name to write the result to
* `"search_pattern"` - the regular expression to search for
* `"replace"` - the replacement string

##### Example

```json
{
  "rules": [
    {
      "comment": "Remove whitespaces from IBAN",
      "fields": [
        "_IBAN"
      ],
      "pattern": "#^(?P<iban_matched>DE[\\d\\s]{21,})$#",
      "actions": [
        {
          "comment": "Remove whitespaces from IBAN",
          "action": "preg_replace",
          "from": "iban_matched",
          "to": "_IBAN",
          "search_pattern": "#\\s#",
          "replace": ""
        }
      ]
    }
  ]
}
```

In this example, the analyser searches for an IBAN starting with `DE` and
followed by at least 21 digits or whitespaces. If the pattern matches, the
analyser removes all whitespaces from the `iban_matched` field and writes the
result to the original `_IBAN` field.

#### `set` Action

The `set` action sets an attribute to a specified value. This can be used, for
example, to set the financial type or the payment instrument of a contribution.

##### Parameters

* `"to"` - the field name to write the result to
* `"value"` - the value to set

##### Example

See the [Regex Analyser Example 3](#regex-analyser-example-3) for an example.

#### `sha1` Action

The `sha1` action reduces a field value to a SHA1 checksum.

##### Parameters

* `"from"` - the field name to read the value from
* `"to"` - the field name to write the hash value to

##### Example

```json
{ 
  "rules": [
    {
      "comment": "Ignore transaction by comparison with IBAN blocklist",
      "fields": [
        "_party_IBAN"
      ],
      "pattern": "#^(?P<iban_matched>DE\\d{20})$#",
      "actions": [
        {
          "comment": "Add salt to IBAN",
          "action": "copy_append",
          "from": "iban_matched",
          "to": "iban_matched",
          "value": "_my_secret_salt"
        },
        {
          "comment": "Hash IBAN",
          "action": "sha1",
          "from": "iban_matched",
          "to": "btx.iban_hash"
        },
        {
          "comment": "Ignore transaction by comparison with IBAN blocklist",
          "action": "lookup:BlockedIbans,blocked,iban_hash",
          "from": "btx.iban_hash",
          "to": "btx.ignore"
        }
      ]
    }
  ]
}
```

Okay, it was hard to come up with a good example for this action. So in this
example, we imagine that there is a custom APIv3 call to check if a certain hash
value is on a blocklist (**This API does not exist!**).

The analyser searches for an IBAN starting with `DE` and followed by 20 digits.
If the pattern matches, the analyser appends a secret salt to the 
`iban_matched` field and writes the result to the `iban_matched` field. Then 
the analyser reduces the `iban_matched` field to a SHA1 checksum and writes the
result to the `btx.iban_hash` field. Finally, the analyser looks up the hash
value via the hypothetical `BlockedIbans` API and writes the result to the 
`btx.ignore` field. An [Ignore Matcher](#ignore-matcher) following this 
analyser could then ignore the transaction if the `btx.ignore` field is set 
to `true`.

#### `sprint` Action

The `sprint` action formats a string using `sprintf`. This can be used, for
example, to prefix a field value with a certain string.

##### Parameters

* `"action"` - the `sprintf` format string:
  Schema: `"sprint:Purpose: %s"`
* `"from"` - the field name to read the value from
* `"to"` - the field name to write the result to

##### Example

```json
{
  "rules": [
    {
      "comment": "Prefix purpose with 'Purpose: '",
      "fields": [
        "purpose"
      ],
      "pattern": "#.*#",
      "actions": [
        {
          "comment": "Prefix purpose with 'Purpose: '",
          "action": "sprint:Purpose: %s",
          "from": "purpose",
          "to": "btx.note"
        }
      ]
    }
  ]
}
```

In this example, the analyser prefixes the value of the `btx.purpose` field
with `Purpose: ` and writes the result to the `btx.note` field. The `btx.note`
could then be propagated to the contribution as a note.

#### `strtolower` Action

The `strtolower` action converts characters to lower case.

##### Parameters

* `"from"` - the field name to read the value from
* `"to"` - the field name to write the result to

##### Example

```json
{
  "rules": [
    {
      "comment": "Convert purpose to lower case",
      "fields": [
        "purpose"
      ],
      "pattern": "#.*#",
      "actions": [
        {
          "comment": "Convert purpose to lower case",
          "action": "strtolower",
          "from": "purpose",
          "to": "btx.purpose_lower"
        }
      ]
    }
  ]
}
```

In this example, the analyser converts the value of the `btx.purpose` field to
lower case and writes the result to the `btx.purpose_lower` field.

#### `unset` Action

The `unset` action unsets the value of a certain field. This can be used, for
example, to remove a field from the transaction data.

##### Parameters

* `"to"` - the field name to unset

##### Example

```json
{
  "rules": [
    {
      "comment": "Unset source field",
      "fields": [
        "source"
      ],
      "pattern": "#.*#",
      "actions": [
        {
          "comment": "Unset source",
          "action": "unset",
          "to": "btx.source"
        }
      ]
    }
  ]
}
```

This example unsets the `btx.source` field.

### Configuration Examples

* [Regex Analyser Example 1](#regex-analyser-example-1) - get donor contact_id from donor_e-mail
* [Regex Analyser Example 2](#regex-analyser-example-2) - find contribution by custom payment reference field
* [Regex Analyser Example 3](#regex-analyser-example-3) - find contribution by payment reference code
* [Regex Analyser Example 4](#regex-analyser-example-4) - find contribution by external transaction ID

#### Regex Analyser Example 1

```JSON
{
  "rules": [
    {
      "comment": "get donor contact_id from donor_e-mail",
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
  ]
}
```

This looks at the `donor_e-mail` attribute (created during the import), and
matches it against the regex `"/^(?P<d_email>.+@.+)/"`. If that match is
successful, (anything containing `@` - a very rudimentary syntax check for
email addresses), the result is being stored temporarily in the `d_email` field.

Note that `d_email` is not automatically stored as an attribute. The first of
the actions copies the matched value `d_email` to the
attribute `matched_d_email`. In this case, the e-mail address is already
available, but creating a new attribute helps with debugging since we can see
what data the second action is working with.

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

The `lookup` action shown above calls the `Contact.getsingle()` API which
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

#### Regex Analyser Example 2

Below is a sample, which analyses a custom payment reference field.
First it looks up the contribution by its custom field value and in the second
step it uses the found `contribution_id` to look up the corresponding contact.
Do not forget to use the API Explorer to see the correct call parameters.

```JSON
{
  "rules": [
    {
      "comment": "find contribution by custom payment reference field ('custom_111' in this example)",
      "fields": [
        "purpose"
      ],
      "pattern": "#(?P<matching_id>[0-9]{12})#",
      "actions": [
        {
          "comment": "look up contribution by custom field",
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
          "value": "matching id: "
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

* `fields` - analyses the purpose field, which has been populated by the
  importer (your payment reference import field must be matched as purpose)
* `pattern` - the RegEx pattern(s) to look for and creates variable(s), which
  will be modified and set in the analysing process.
* all `action` elements, as well as their definition and possible usage are
  described in details below.

#### Regex Analyser Example 3

Assume that we have provided our donors with a payment reference code (e.g.
pre-printed on a transfer form) that allows us to identify the corresponding
contact and a specific campaign that we want to associate with the incoming
donation. As the donation is made without prior notice, we would want to create
a new contribution for which we first need to find all the necessary
information.

The payment reference code contains the `contact_id` and
the `external_identifier` of a campaign. To ensure that the code is valid, a
checksum is calculated by concatenating the `contact_id` with
the `external_identifier` and then calculating the modulo 97 of the resulting
string.

> [!NOTE]
> An example payment reference code provided by the donor in the `purpose`
> field: `X101X202X61X`
>
> The payment reference contains the `contact_id` **101** and
> the `external_identifier` **202** of a campaign. The checksum was calculated
> by concatenating the `contact_id` with the `external_identifier` and then
> calculating the modulo 97 of the resulting string (101202 mod 97 = 61).

The regex pattern to find the variables could look like this:

```regexp
\/X(?P<contact>[\\d]+)X(?P<campaign_code>[\\d]+)X(?P<checksum>[\\d]+)X\/i
```

The plugin can verify the `checksum` by using the `calculate` action.

The configuration could look like this:

```json
{
  "rules": [
    {
      "comment": "Process payment reference code",
      "fields": [
        "purpose"
      ],
      "pattern": "\/X(?P<contact>[\\d]+)X(?P<campaign_code>[\\d]+)X(?P<checksum>[\\d]+)X\/i",
      "actions": [
        {
          "comment": "Set financial type",
          "action": "set",
          "value": "1",
          "to": "financial_type_id"
        },
        {
          "comment": "Set payment instrument",
          "action": "set",
          "value": "10",
          "to": "payment_instrument_id"
        },
        {
          "comment": "Validate Code, Step 1",
          "action": "copy",
          "from": "contact_id",
          "to": "code_is_valid"
        },
        {
          "comment": "Validate Code, Step 2",
          "action": "copy_append",
          "from": "campaign_code",
          "to": "code_is_valid"
        },
        {
          "comment": "Validate Code, Step 3",
          "action": "calculate",
          "from": "(((int) \"{code_is_valid}\") % 97) == (((int) \"{checksum}\") % 97)",
          "to": "code_is_valid"
        },
        {
          "comment": "Lookup campaign_id by external_identifier",
          "action": "lookup:Campaign,id,external_identifier",
          "from": "campaign_code",
          "to": "campaign_id"
        },
        {
          "comment": "Set the contribution source by looking up the campaign title",
          "action": "lookup:Campaign,title,external_identifier",
          "from": "campaign_code",
          "to": "source"
        }
      ]
    }
  ]
}
```

Now that we have all the information needed to create a contribution, a separate
**Create Contribution Matcher** plugin could check for the presence of the
`code_is_valid` field and determine if it is `true`. Then the matcher can pass
the other fields found via `value_propagation` to the new contribution and
create it fully automatically:

```json
{
  "auto_exec": 1,
  "factor": 1,
  "threshold": 1,
  "required_values": [
    "btx.code_is_valid",
    "btx.source",
    "btx.financial_type_id",
    "btx.payment_instrument_id"
  ],
  "value_propagation": {
    "btx.source": "contribution.source",
    "btx.financial_type_id": "contribution.financial_type_id",
    "btx.campaign_id": "contribution.campaign_id",
    "btx.payment_instrument_id": "contribution.payment_instrument_id"
  }
}
```

#### Regex Analyser Example 4

Below is an example where an external transaction ID (`trxn_id`) provided by a
payment service provider is used as a lookup field.

Let's assume, the payment service provider notifies us immediately about a
payment, which then is automatically created as a pending contribution in
CiviCRM. The pending contribution contains the external transaction ID in
its `trxn_id` field. CiviBanking now would have to match an incoming bank
transaction with the pending contribution and set the status to complete.

Here is the configuration of the Regex Analyzer:

```JSON
{
  "rules": [
    {
      "comment": "Find and complete contribution by external transaction ID",
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
        }
      ]
    }
  ]
}
```

- The Plugin first tries to find the external transaction ID in the purpose
  field and writes it to the `twingle_trxn_id_matched` field.
- Next it uses `preg_replace` to sanitize the input by stripping all whitespaces
  from the `twingle_trxn_id_matched` field. The result is written to
  the `twingle_trxn_id` field.
- Finally, the `twingle_trxn_id` is used to look up the contribution by its
  external transaction ID `trxn_id`. The result is written to
  the `contribution_id` field.

Afterwards, a contribution matcher following below this plugin can use the
`contribution_id` found to complete the pending contribution. You can find
an example for such a Contribution Matcher in
the [Contribution Matcher section](#contribution-matcher).

## Account Lookup Analyser

> [!NOTE]
> This section is yet to be completed.

# Matchers

When creating new matchers, make sure that they run in the exact order in which
you want the transaction to be evaluated.

PLEASE REVIEW: When one matcher identifies/processes a transaction, all other
matchers will not run on this transaction.

## Default Options Matcher

This matcher provides the default processing options for manual reconciling:

* A list of contacts, looked up by name, with the option to: enter existing
  contribution ID to be matched or create a new contribution for the contact.
* Ignore the transaction at all, as it does not belong to CiviCRM.

> [!IMPORTANT]
> If you set any fields in your analyzers, you probably want to ensure that not 
> only your custom matchers pass them on to the value propagation, but also 
> the standard matcher. Otherwise, the custom fields will not be available as 
> prefilled values in the manual creation form. 
> 
> Also make sure that you put the propagation values **for the Default 
> Matcher** into the `createnew_value_propagation` array and not into the 
> `value_propagation` array, otherwise the values will not appear prefilled in 
> the manual creation form, but will overwrite the manually made entries when 
> the contribution is saved.

## Create Contribution Matcher

The purpose of this matcher is to populate a `contribution` namespace with the
values needed to create a contribution. Depending on the options selected, this
will either be automatically executed or presented to the user to select the
desired action.

Since the matchers are sometimes dealing with uncertain data, they make use of a
weighing system to propose the "best" matches to the user. For example, a bank
statement may include a payer's name, but it is unlikely to match exactly with
that in CiviCRM. Small deviations should be regarded as more likely to be a
correct match than large ones.

Where the matching is being done on a unique ID, we can be confident that
the correct CiviCRM record is being selected. In this case, we do not need the
complexity of the probability system.

So far, attribute names have been mostly arbitrary and have been simple
names: `fundraiser_user_name`. In fact, all these live in the `btx` ("Bank
Transaction") namespace and are referred to in these plugins
as `btx.fundraiser_user_name`.

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

## Contribution Matcher

The purpose of this matcher is to match a transaction to **an existing 
contribution** which then can be completed. Depending on the options selected,
this will either be automatically executed or presented to the user to select
the desired action.

### Configuration Parameters

The configuration parameters can be set within the `config` object of the
Matcher Plugin. The following sections describe the different configuration
parameters and their possible values.

#### Main Parameters

| Configuration Parameter        | Type                                   | Default                                                                         | Description                                                                                                         |
|--------------------------------|----------------------------------------|---------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `accepted_contribution_states` | JSON array: `["list", "of", "states"]` | `["Completed", "Pending"]`                                                      | Contribution states which are accepted by the matcher.                                                              |
| `auto_exec`                    | float: `0` - `1.0`                     | `false`                                                                         | Confidence level above which the matcher automatically sets the contribution to complete. Set `false`to deactivate. |
| `lookup_contact_by_name`       | JSON object: `{"key": "value"}`        | `{"soft_cap_probability": 0.8, "soft_cap_min": 5, "hard_cap_probability": 0.4}` |                                                                                                                     |
| `received_date_check`          | boolean: `true` or `false`             | `true`                                                                          |                                                                                                                     |
| `request_amount_confirmation`  | boolean: `true` or `false`             | `false`                                                                         | If true, user confirmation is required to reconcile differing amounts.                                              |
| `required_values`              | JSON array: `["list", "of", "values"]` | -                                                                               | Fields which are required to be present in the `btx` namespace.                                                     |
| `threshold`                    | float: `0` - `1.0`                     | `0.5`                                                                           | Confidence level below which the matcher will discard the contribution.                                             |
| `title`                        | string: `"Title"`                      | -                                                                               | Title of the matcher.                                                                                               |
| `value_propagation`            | JSON object: `{"key": "value"}`        | -                                                                               | Fields which should be copied from the `btx` namespace to the contribution.                                         |

#### Contribution Search

| Configuration Parameter | Type                       | Default | Description                                                                                                       |
|-------------------------|----------------------------|---------|-------------------------------------------------------------------------------------------------------------------|
| `contribution_search`   | boolean: `true` or `false` | `true`  | Whether the matcher should search for any matching contributions by the contact name provided in the transaction. |
| `contribution_list`     | string: `"field_name"`     | -       | Field that holds a comma separated list of contribution IDs provided by the analyser plugins.                     |

#### Date Check

| Configuration Parameter | Type                                                                            | Default       | Description                                                                                                                                                       |
|-------------------------|---------------------------------------------------------------------------------|---------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `received_date_check`   | boolean: `true` or `false`                                                      | `true`        | Whether the matcher should check the received date of the contribution.<br/>WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW                              |
| `received_range_days`   | integer: `366`                                                                  | `366`         | Number of days before the transaction date to search for matching contributions.<br/>WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW                     |
| `received_date_minimum` | [date string](https://www.php.net/manual/en/datetime.formats.php): `"-5 weeks"` | `"-100 days"` | Minimum date of the contribution relative to the transaction date. Gets parsed with [strtotime()](https://www.php.net/manual/en/function.strtotime.php) function. |                                                                                                                       
| `received_date_maximum` | [date string](https://www.php.net/manual/en/datetime.formats.php): `"+1 week"`  | `"+1 days"`   | Maximum date of the contribution relative to the transaction date. Gets parsed with [strtotime()](https://www.php.net/manual/en/function.strtotime.php) function. |                                                                                                                       

#### Amount Check

| Configuration Parameter   | Type                       | Default | Description                                                                                      |
|---------------------------|----------------------------|---------|--------------------------------------------------------------------------------------------------|
| `amount_check`            | boolean: `true` or `false` | `true`  | Whether the matcher should check the amount of the contribution to match the transaction amount. |
| `amount_relative_minimum` | float: `1.0`               | `1.0`   | Minimum relative amount of the contribution to match the transaction amount.                     |
| `amount_relative_maximum` | float: `1.0`               | `1.0`   | Maximum relative amount of the contribution to match the transaction amount.                     |
| `amount_absolute_minimum` | float: `1.0`               | `0`     | Minimum absolute amount of the contribution to match the transaction amount.                     |
| `amount_absolute_maximum` | float: `1.0`               | `1.0`   | Maximum absolute amount of the contribution to match the transaction amount.                     |

#### Penalties

| Configuration Parameter      | Type               | Default | Description                                                                                                                               |
|------------------------------|--------------------|---------|-------------------------------------------------------------------------------------------------------------------------------------------|
| `date_penalty`               | float: `0` - `1.0` | `1.0`   | Penalty to apply to the confidence level if the date of the contribution does not match the transaction date.                             |
| `payment_instrument_penalty` | float: `0` - `1.0` | `0`     | Penalty to apply to the confidence level if the payment instrument of the contribution does not match the transaction payment instrument. |
| `financial_type_penalty`     | float: `0` - `1.0` | `0`     | Penalty to apply to the confidence level if the financial type of the contribution does not match the transaction financial type.         |
| `amount_penalty`             | float: `0` - `1.0` | `1.0`   | Penalty to apply to the confidence level if the amount of the contribution does not match the transaction amount.                         |
| `currency_penalty`           | float: `0` - `1.0` | `0.5`   | Penalty to apply to the confidence level if the currency of the contribution does not match the transaction currency.                     |

The penalties are calculated from the magic fields `value_date`, `payment_instrument` (not: `payment_instrument_id`), `financial_type_id`, `amount` and `currency`. You need to set these fields accordingly to use the penalties.

#### Cancel Reason

| Configuration Parameter              | Type                       | Default           | Description                                                                                        |
|--------------------------------------|----------------------------|-------------------|----------------------------------------------------------------------------------------------------|
| `cancellation_cancel_reason`         | boolean: `false`           | `false`           | If `mode` ist set to `"cancellation"`, the cancellation reason can be displayed in the suggestion. |
| `cancellation_cancel_reason_edit`    | boolean: `true`            | `true`            | If set to `true` the user can edit the cancellation reason in the suggestion.                      |
| `cancellation_cancel_reason_source`  | string: `"field_name"`     | `"cancel_reason"` | Field that holds the cancellation reason.                                                          |
| `cancellation_cancel_reason_default` | string: `"Default Reason"` | `"Unknown"`       | If the field defined in `cancellation_cancel_reason_source` is empty, this default value is used.  |

The Contribution Matcher is used to match against existing contributions. A
matched contribution can then be set to complete, either automatically or
by creating a suggestion that can be approved manually.

### Contribution Matcher Example

A simple example for matching against existing contributions which were already
identified by an analyser:

```JSON
{
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
    "btx.purpose": "contribution.note"
  },
  "contribution_list": "contribution_id",
  "contribution_search": false
}
```

* `auto_exec` defines the confidence level above which the matcher
  automatically sets the contribution to complete.

* `threshold` defines the confidence level below which the matcher will
  discard the contribution.

* `accepted_contribution_states` defines the contribution states which are
  accepted by the matcher. In this example the matcher will only match against
  contributions with the status _pending_ or _partially paid_.

* `required_values` defines the fields which are required to be present in the
  `btx` namespace. In this example the matcher requires the `contribution_id` to
  be present.

* `value_propagation` defines the fields which should be copied from the `btx`
  namespace to the contribution. In this example the `purpose` field is copied
  to the `note` field of the contribution.

* `contribution_list` defines a field that holds a comma separated list of
  contribution IDs provided by the analyser plugins. All contributions in this
  list will be checkt against the above defined `accepted_contribution_states`
  and they are rated by comparing the _date_, _amount_ and _payment instrument_
  of the contribution with the actual transaction data. If the resulting
  confidence level is above the `threshold`, the contribution will be suggested
  for completion, or it will be completed automatically if the confidence level
  is above the `auto_exec` value.

* `contribution_search` defines whether the matcher should search for any
  matching contributions by the contact name provided in the transaction or if
  it instead should only get applied to the contribution IDs provided in
  the `contribution_list`. If `contributions_search` is set to `true`, the
  contributions in the `contribution_list` will still be treated additionally.

## Recurring Contribution Matcher

> [!NOTE]
> This section is yet to be completed.

## SEPA Matcher Plugin

You can see example configurations in the configuration database.

You can see example configurations in the configuration database.

> [!NOTE]
> This section is yet to be completed.

## Membership Matcher

You can see example configurations in the configuration database.

You can see example configurations in the configuration database.

> [!NOTE]
> This section is yet to be completed.

## Ignore Matcher

With an ignore matcher you can identify transactions, which should be
automatically ignored by Civi Banking, for example if one bank statement/import
file can contain outgoing payments, fees, internal financial transactions.

### Ignore Matcher Example

An Ignore Matcher which ignores transactions by specific business transaction 
codes:

```JSON
{
  "auto_exec": 1,
  "ignore": [
    {
      "field": "GVC",
      "regex": "#^106$#",
      "message": "SEPA Cards Clearing"
    },
    {
      "field": "GVC",
      "regex": "#^116$#",
      "message": "SEPA Credit Transfer "
    },
    {
      "field": "GVC",
      "regex": "#^177$#",
      "message": "SEPA Credit Transfer Online"
    },
    {
      "field": "GVC",
      "regex": "#^808$#",
      "message": "Fees"
    }
  ]
}
```

* `auto_exec` defines that the matcher should automatically ignore the
  transaction if it matches one of the ignore rules
* `ignore` defines the ignore rules
  * `field` defines the field to match against
  * `regex` defines the regex to match against
  * `message` defines the message to display in the ignore reason
* `dont_ignore` defines which transactions should not be ignored, although they
  match one of the ignore rules
  * `field` defines the field to match against
  * `regex` defines the regex to match against

## Batch Matcher

You can see example configurations in the configuration database.

> [!NOTE]
> This section is yet to be completed.
