## Reference

###  Actions

the following actions can be used in Civi Banking Plugins. Not every action can be used everywhere. 
For Importer examples reference [importer actions](configuration/plugins/importer-actions.md)

| name 				| description |Importer|RegexAnalyser|
| ----------- 		| ----------- |-----------|-----------|
|  set				| Sets a variable by reading from an Bank transaction   		  |XMl,CSV|yes|
|  amount			| Similar to set, but used when its known that the variable is an amount. Replaces all "," with "."|XMl,CSV|     	  
|  amountparse		| Similar to set, but used when its known that the variable is an amount. Removes thousand seperator dots and commas. Then Replaces all "," with "."|CSV|    	  
|  strtotime		| Similar to set, but used for datetimes      	  |	XMl,CSV|
|  align_date		| Align a date forwards or backwards  by 1 Day align_date:backwards -1 Day, default +1 Day |XMl,CSV|yes|	  
|  replace			| This action is doing an in-place replacement of a string into another string. The source string is specified directly after the replace string. The replacement string is specified directly after the source string.|XMl,CSV|
|  regex			| Similar to set, but using a regular expression for extracting specific pieces of information|CSV|
|  trim				| Takes out a given character from a given string     	  |XMl,CSV|yes|
|  append			| Appends on variable after another, seperated by a character defined in type|XMl,CSV|
|  unset			| Removes a variable from the namespace|XML|yes|
|  format			| Formats a variable using the [sprintf](http://php.net/manual/en/function.sprintf.php) function.|XMl,CSV| 
|  constant			| Assigns a value to a variable|XMl,CSV| 
|  calculate		| runs a PHP expression (CAUTION!)||yes| 
|  copy				| copies the attribute value to another attribute|CSV
|  copy_append		| appends the value to an existing attribute||yes|  
|  copy_ltrim_zeros	| copies and removes leading zeros||yes|  
|  lookup			| copies the attribute value to another attribute ||yes|  
|  map				| maps from the matched value to a new value | |yes| 
|  preg_replace		| substitutes part of the string using a regular expression ||yes|  
|  strtolower 		| converts characters to lower case ||yes|  
|  sha1				| reduce to SHA1 checksum||yes|  
|  sprint:			| format data||yes| 
|  api:				| Look up parameters via API call||yes| 
  
###  Flow of Control
| name 			| description |CSV Importer	|XML Importer	|
| ----------- 	| ----------- | ----------- |----------- |
| if			| the action of a group only happens if evalutes True|equalto, matches|=,!=,<,>,<=,>=,IN,!IN|

###  Project 60 Data Model

The following concepts are represented (entities are written without the leading civicrm_):

| entity name 				| shortcode		| description |
| ----------- 				| ----------- 	| ----------- | 
|  bank_transaction			|BTX     		|an individual movement in a bank statement (for a semantic discussion on the terms payment, refund, ... see the detailed description of this entity)			  | 
|  bank_account				|BA        		|an individual bank account			  | 
|  bank_account_reference	|BAR        	|an external reference for an individual bank account		  | 
|  bank_transaction_batch	|BTXB        	|a set of bank transactions, typically from a single bank statement| 

####  civicrm_bank_transaction
Represents a single bank transaction (BTX), ie. an entry in the communication from the bank concerning things that happen to the bank account. Could be a money transfer into/out of the BA, a message saying there is interest to be booked, ...

| field			| type			| key 		  | description|
| ----------- 	| ----------- 	| ----------- | -----------|
| id			|int(11) 		|PK			  | unique auto-incrementing reference|
| ba_id			|int(11) 		|FK			  | link to civicrm_bank_account.id (internal / target / recipient account)|
| type_id		|int(11) 		|FK			  | links to a type (one of a set of btxtypes)|
| value_date	|date 		    |			  | describes the moment the value of this transaction(BTX) was added to the BA balance|
| booking_date	|date 		    |			  | execution date of the transaction(BTX)|
| amount		|decimal(20,2)	|			  | amount of the transaction(BTX)|
| currency		|varchar(3)	    |			  | currency of the transaction(BTX)|
| status		|int(11)	    |FK			  | link to a set of status values : new, identified, processed, ignored|
| data_raw		|text	    	|			  | the complete information that was received for this transaction(BTX)|
| data_parsed	|text	    	|			  | a JSON-formatted array of decoded fields (built by the specific plugin)|
| party_ba_id	|int(11)	    |FK			  | link to civicrm_bank_account:id if there is a second party involved in the transaction (BTX) (typically in case of transfer type BTX)|
| sequence		|int(11)	    |			  | a relative reference inside its originating source batch (requires the batch to be identified)|
| tx_batch_id	|int(11)	    |FK			  | to the originating batch (not sure whether the batch_entity does not take care of this)|
| bank_reference|varchar(64)	|		  	  | a unique external reference used by the bank, stored for reconciliation and integrity maintenance purposes. Every import plugin MUST provide such a reference, even if it generates a hash value of sorts.|

!!! v2 might have a n:n link between types and transactions, effectively implementing some sort of tagging. If we are able to create a tagset that is used for BTX, we could directly use this functionality (using entity_tag). Either way, for now, the API/BAO can return the types as an array of one.

####  civicrm_bank_account
Most bank_account records will describe parties (ie. people paying money to the organization). They will have the basic fields :

| field			| type			| key 		  | description|
| ----------- 	| ----------- 	| ----------- | -----------|
| id			|int(11) 		|PK			  | unique auto-incrementing reference|
| data_raw		|text	    	|			  | the information found in the latest  transaction (BTX)relating to this  account (BA) (typically as a party) describing the identity/ies of its owner(s), like address information|
| data_parsed	|text	    	|			  | a JSON-formatted array of decoded fields (built by the specific plugin)|
| contact_id	|int(11)	    |FK			  | link to civicrm_contact:id describing the owner of the bank account (BA)|
| create_date	|date	    	|		  	  | |
| modified_date	|date	    	|			  | |
| description	|varchar(255)	|			  | a human readable description of the bank account (BA), eg.'Online donation account'|

In Belgium, bank statements contain address info, but other countries have different practices. We'll remove the address_id previously modeled for two reasons : (1) we can store it in the parsed blob and (2) CiviCRM addresses are currently hardwired to link to a contact, and we do not want to create another type of contact for a bank account.

The description will mainly be useful for managed bank accounts (accounts for which bank input is expected), but may also be used to allow a donor to 'name' their accounts using an online account profile.

####  civicrm_bank_account_reference
We will allow for different references for a single bank account (such as IBAN, BIC and BBAN), so we'll use a table which maps n:n with references / type tuples. 

| field			| type			| key 		  | description|
| ----------- 	| ----------- 	| ----------- | -----------|
| id			|int(11) 		|PK			  | unique auto-incrementing reference|
| reference		|varchar(64) 	|		      | the reference with which the bank account (BA) is identified (IBAN, BBAN, ...)|
| reference_type|int 		    |			  | the type of account reference (IBAN, BBAN, BIC ...) from an enum value|
| ba_id			|int 		    |FK			  | |

####  civicrm_bank_transaction_batch
Information coming from banks
We'll assume every set of transactions belongs to a sort of bank statement. This ensures we maintain some form of integrity of the data. Bank statements will typically be easy to wrap in this model. Manual input of a bank statement would be a case similar to an imported bank statement.

| field				| type			| key 		  | description|
| ----------- 		| ----------- 	| ----------- | -----------|
| id				|int(11) 		|PK			  | unique auto-incrementing reference|
| issue_date		|datetime 		|		      | when the statement was issued|
| reference			|varchar(32)    |			  | human readable version of the statement reference (if available)|
| sequence			|int 		    |		  	  | used by the import plugin responsible for maintaining integrity|
| starting_balance 	|decimal(20,2) 	|		  	  | |
| ending_balance 	|decimal(20,2) 	|		  	  | |
| tx_count 			|int 			|		  	  | |
| starting_date		|datetime 		|		  	  | used for user identification / documentation purpose only|
| ending_date		|datetime 		|		  	  | used for user identification / documentation purpose only|
| currency			|varchar(3) 	|		  	  | |

Information from accounting systems
Exports from accounting systems may or may not work with a sequence validation. The main difference is that contrary to a bank statement, the group itself does not contain validation information to avoid duplicates. 

