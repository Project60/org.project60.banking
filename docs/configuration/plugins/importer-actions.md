## Opening / generic elements

```json
{
  "comment": "CAMT.53 Import configuration (BNP Paribas Fortis)",
  "defaults": {
    "payment_instrument_id": "4"
  },
  "namespaces": {
    "camt": "urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"
  },
  "probe": "camt:BkToCstmrStmt/camt:GrpHdr/camt:MsgId"
}
```

### Comments

We start of by adding a comment so that it will make our lives easier in case we
have many importers at a later stage. Note: Comment tags are always *optional*.
But we really point out to put comments in order to remind you about the purpose
of your current element/group.

### Defaults

The group **Defaults** is used to enclose definitions for some standard piece of
information about the incoming transactions that will be added to **all** the
upcoming transactions of the file that we will be importing.

In this example, we specify that by default the attribute of *
*payment_instrument_id** will always be the number '4' (unless we alter it
during the matcher chapter)

!!! note
    The *payment_instrument_id* is really important and should be configured
    correctly. This means that the ID should exist in your database and reflect
    the payment instrument you want to use for the incoming transactions, for
    example Bank Transfer.

## List of supported actions

### set

#### Description

Sets a variable with a fixed value, by reading a value in column (named by
column number or name in the first row) of a CSV file or an XPATH in XML (if
xpath: is defined in the from) or another variable

#### Parameters

* "from": Defines which element from the CSV column, XML Path OR variable to
  read so that it can find the variable. If our definition is starting
  with `xpath` then we're directing the importer to find that XPATH instead of a
  variable.
* "to": Which variable to use to store the altered value.

#### Examples

```json
{
   "comment": "CSV: IBAN Incoming account",
   "from": "IBAN",
   "to": "_IBAN",
   "type": "set"
}
```

```json
{
  "comment": "XML: Get the name from XPATH and store it to variable name",
  "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Acct/camt:Id/camt:IBAN",
  "to": "tx.name",
  "type": "set"
}
```

```json
{
  "comment": "Get the value from another variable and store it to the new variable",
  "from": "temp_name",
  "to": "tx.name",
  "type": "set"
}
```

### amount

#### Description

Although a little bit similar to the `set` action, amount is used when we know
that our variable is an amount. The difference is that there are special
formatters being used so that it can treat properly the thousands/decimals
separators.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with `xpath` then we're
  directing the importer to find that XPATH instead of a variable.
* "to": Which variable to use to store the altered value.

#### Examples

```json
{
  "from": "CSV: Column amount",
  "to": "amount",
  "type": "amount"
}
```

```json
{
  "comment": "XML statement amount",
  "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[2]/camt:Amt",
  "to": "amount",
  "type": "amount"
}
```

### strtotime

#### Description

Fields with dates have their own action, called "strtotime" which does the
datetime handling on its own. Similar to the `set` action, they also require 2
parameters to work, `from` and `to`. Usually you want to store the
variable `booking_date` and `value_date` here.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with `xpath` then we're
  directing the importer to find that XPATH instead of a variable.
* "to": Which variable to use to store the altered value.

#### Examples

```json
 [
  {
    "comment": "CSV: column booking-date"
    "from": "bookingdate",
    "to": "booking_date",
    "type": "strtotime:d.m.y"
  },
  {
    "comment": "CSV: column Valuta",
    "from": "Valuta",
    "to": "value_date",
    "type": "strtotime:d.m.y"
  }
]
```

```json
{
  "comment": "booking date",
  "from": "xpath:camt:BookgDt/camt:Dt",
  "to": "booking_date",
  "type": "strtotime"
}
```

### replace

#### Description

This action is doing an in-place replacement of a string into another string.
The source string is specified directly after the `replace` string. The
replacement string is specified directly after the source string.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with an `xpath` string,
  then we're directing the importer to find that variable in the transaction
  entry, otherwise it will bring back the variable defined.
* "to": Which variable to use to store the altered value.
* "type": replace:<source_string>:<target_string>

#### Examples

```json
{
  "comment": "DBIT means negative, replace this in the amount",
  "from": "amount",
  "to": "amount",
  "type": "replace:DBIT:-"
}
```

### regex

#### Description

Similar to the `set` action, the `regex` action does what it exactly implies:
Using a regular expression, it extracts the piece of information that was
requested.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with an `xpath` string,
  then we're directing the importer to find that variable inside the specified
  XPATH, otherwise, look into other already processed variables.
* "to": Which variable to use to store the altered value.
* "type": `regex:<regex_expression>`

#### Examples

```json
{
  "comment": "party address",
  "from": "xpath:camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]|camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]",
  "to": "postal_code",
  "type": "regex:#^(\\d{4}) +\\w+#"
}
```

!!! notes
    Keep in mind that you need to escape backslashes by using twice the
    backslash

### trim

#### Description

Takes out the (trims) a given character from a given string.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with an `xpath` string,
  then we're directing the importer to find that variable inside the specified
  XPATH, otherwise, look into other already processed variables.
* "to": Which variable to use to store the altered value.
* "type": `trim:<character_to_trim>`

#### Examples

```json
{
  "comment": "Trim whitespaces from the name field",
  "from": "name",
  "to": "name",
  "type": "trim: "
}
```

### append

#### Description

Appends on variable after another by using a character defined in type type

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with an `xpath` string,
  then we're directing the importer to find that variable inside the specified
  XPATH, otherwise, look into other already processed variables.
* "to": Which variable to use to append the altered value.
* "type": `append:<character_to_append_between_variables>`

#### Examples

```json
{
  "comment": "Appends number to the street address",
  "from": "address_number",
  "to": "street_address",
  "type": "append: "
}
```

### unset

#### Description

Unlike `set`, this action **removes** complete a variable from the namespace.

#### Parameters

* "to" : Which variable to unset

#### Examples

```json
{
  "comment": "unset variable postal_code",
  "to": "postal_code",
  "type": "unset"
}
```

### format

#### Description

Formats a variable using the [sprintf](http://php.net/manual/en/function.sprintf.php) function.

#### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it
  can find the variable. if our definition is starting with an `xpath` string,
  then we're directing the importer to find that variable inside the specified
  XPATH, otherwise, look into other already processed variables.
* "to": Which variable to use to store the altered value.
* "type": `format:<formatting_parameters>`

#### Examples

```json
{
  "comment": "Format the variable into",
  "from": "tmp_identifier",
  "to": "final_identifier",
  "type": "format:%010d"
}
```

Formats the variable `tmp_identifier ` into a 10-digit variable, adding
prepending zeros and then stores it into the variable `final_identifier`

### constant

#### Description

Assigns a value to the variable

#### Parameters

* "from": A value that will be set in the 'to' variable.
* "to": Which variable to use to store the altered value.

#### Examples

```json
{
  "comment": "Set campaign_id",
  "from": "28",
  "to": "campaign_id"
}
```

### Flow of control: if

You may add an if statement to a group to apply the group only if a certain case
exists.

#### Examples

```json
{
  "comment": "CSV: Set a payment_instrument_id on a specific incoming bank account",
  "from": "IBAN",
  "if": "equalto:<a valid IBAN>",
  "type": "replace:<IBAN>:<payment_instrument_id>",
  "to": "payment_instrument_id"
 }
```
