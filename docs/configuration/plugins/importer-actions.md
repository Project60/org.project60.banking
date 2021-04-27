# Importers - List of supported actions

## set

### Description

Sets a variable by reading an XPATH (if xpath: is defined in the from) or another variable

### Parameters

* "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with `xpath` then we're directing the importer to find that XPATH instead of a variable.
* "to": Which variable to use to store the altered value. 

### Examples

```json
{
  "comment": "Get the name from XPATH and store it to variable name",
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

## amount

### Description

Although a little bit similar to the `set` action, amount is used when we know that our variable is an amount. The difference is that there are special formatters being used so that it can treat properly the thousands/decimals separators.

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with `xpath` then we're directing the importer to find that XPATH instead of a variable.
- "to": Which variable to use to store the altered value.

### Examples

```json
{
  "comment": "statement amount",
  "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[2]/camt:Amt",
  "to": "amount",
  "type": "amount" 
}
```

## strtotime

### Description

Fields with dates have their own action, called "strtotime" which does the datetime handling on its own. Similar to the `set` action, they also require 2 parameters to work, `from` and `to`.

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with `xpath` then we're directing the importer to find that XPATH instead of a variable.
- "to": Which variable to use to store the altered value.

### Examples

```json
{
  "comment": "booking date",
  "from": "xpath:camt:BookgDt/camt:Dt",
  "to": "booking_date",
  "type": "strtotime" 
}
```

## replace

### Description

This action is doing an in-place replacement of a string into another string. The source string is specified directly after the `replace` string. The replacement string is specified directly after the source string. 

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with an `xpath` string, then we're directing the importer to find that variable in the transaction entry, otherwise it will bring back the variable defined.
- "to": Which variable to use to store the altered value.
- "type": replace:<source_string>:<target_string>

### Examples

```json
{
  "comment": "DBIT means negative, replace this in the amount",
  "from": "amount",
  "to": "amount",
  "type": "replace:DBIT:-" 
}
```

## regex

### Description

Similar to the `set` action, the `regex` action does what it exactly implies: Using a regular expression, it extracts the piece of information that was requested. 

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with an `xpath` string, then we're directing the importer to find that variable inside the specified XPATH, otherwise, look into other already processed variables.
- "to": Which variable to use to store the altered value.
- "type": `regex:<regex_expression>`

### Examples

```json
{
  "comment": "party address",
  "from": "xpath:camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]|camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]",
  "to": "postal_code",
  "type": "regex:#^(\\d{4}) +\\w+#" 
}
```

!!! notes

Keep in mind that you need to escape backslashes by using twice the backslash

## trim

### Description

Takes out the (trims) a given character from a given string.

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with an `xpath` string, then we're directing the importer to find that variable inside the specified XPATH, otherwise, look into other already processed variables.
- "to": Which variable to use to store the altered value.
- "type": `trim:<character_to_trim>`

### Examples

```json
{
  "comment": "Trim whitespaces from the name field",
  "from": "name",
  "to": "name",
  "type": "trim: " 
}
```

## append

### Description

Appends on variable after another by using a character defined in type type

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with an `xpath` string, then we're directing the importer to find that variable inside the specified XPATH, otherwise, look into other already processed variables.
- "to": Which variable to use to append the altered value.
- "type": `append:<character_to_append_between_variables>`

### Examples

```json
{
  "comment": "Appends number to the street address",
  "from": "address_number",
  "to": "street_address",
  "type": "append: "
}
```

## unset

### Description

Unlike `set`, this action **removes** complete a variable from the namespace.

### Parameters

- "to" : Which variable to unset

### Examples

```json
{
  "comment": "unset variable postal_code",
  "to": "postal_code",
  "type": "unset" 
}
```

## format

### Description

Formats a variable using the [sprintf](http://php.net/manual/en/function.sprintf.php) function.

### Parameters

- "from": Defines which element from the XML Path OR variable to read so that it can find the variable. if our definition is starting with an `xpath` string, then we're directing the importer to find that variable inside the specified XPATH, otherwise, look into other already processed variables.
- "to": Which variable to use to store the altered value.
- "type": `format:<formatting_parameters>`

### Examples

```json
{
  "comment": "Format the variable into",
  "from": "tmp_identifier",
  "to": "final_identifier",
  "type": "format:%010d" 
}
```

Formats the variable `tmp_identifier ` into a 10-digit variable, adding prepending zeros and then stores it into the variable `final_identifier`

## constant

### Description

Assigns a value to the variable

### Parameters

- "from": A value that will be set in the 'to' variable.
- "to": Which variable to use to store the altered value.

### Examples

```json
{
  "comment": "Set campaign_id",
  "from": "28",
  "to": "campaign_id"
}
```



---

~docver: 0.1~