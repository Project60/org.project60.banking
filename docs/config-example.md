!!! note
    This documentation section has been created with CiviCRM 5.6.0 in Drupal 7 with CiviBanking 0.7
    
##Introduction

CiviBanking is a great extension that can read and interpret files with payments you can get from your bank or payment provider. So this could be a file from your bank, but also a downloaded file from Mollie, PayPal or Visa.
 
The extension will import the data, transform it to generic transactions that it can process and then match those with the related contributions in CiviCRM and mark them as completed (or suggest you create new contributions or even contacts). And for quite a few scenario's it can do this automatically too!

Configuring CiviBanking is a bit of a job. As it is pretty powerful and can handle different types of files, it also needs configuration for each file. And as you are importing files from specific formats it also requires quite some technical knowledge to understand all the steps required.

!!! note "What if I want to know more?"
    Please note that this chapter does not mention all possibilities or options. CiviBanking is very powerful and flexible, trying to write a guide that mentions all possibilities would be a massive task. The amibition of this section is to give you an example configuration and explain the concepts. If you have specific needs or additional questions please contact an expert to discuss what is possible?
    
    It might well be that you have read this chapter and think this is a far too daunting step for you to take. If that is the case, please contact a CiviCRM expert to help you. You can find CiviCRM experts on the Find an Expert page on the [CiviCRM website](https://civicrm.org/partners-contributors).

!!! note "Contribute?"
    If you would like to contribute to the documentation of CiviBanking you can certainly do so! Every  now and then sprints to update the documentation are organised. In most cases this will be announced on the CiviCRM channels. If you want to make sure you are invited for the next one please drop [betty.dolfing@civicoop.org](mailto:betty.dolfing@civicoop.org) or [erik.hommel@civicoop.org](mailto:erik.hommel@civicoop.org) a mail.

Technically speaking there are 3 steps in the CiviBanking process when reading and processing payments:

1. Importing
1. Analyzing
1. Matching

The **first step (importing)** is translating the data from the original format to the basic generic format that CiviBanking understands. It is a bit as if CiviBanking can understand Esperanto, and can do all the following steps as long as the data is in Esperanto. The **importing** step will translate the dialect of your bank or other payment service into the CiviBanking Esperanto.

The **second step (analyzing)** reads the transaction from the payments file and checks what it could find and do related to your CiviCRM installation. For example: 

* the payment could be linked to a certain contribution (will be the most common case)
* no contribution but there is a contact with the same name and bank account, and a new contribution could be created
* no contribution but there is a contact with the same name but no bank account, and a new contribution could be created
* the payment has nothing to do with CiviCRM
* etc. etc.

In the **third step (matching)** you pick one of the suggestions from the analyzing step and process that into CiviCRM (or configure the matcher to do so automatically).

We will look into each step in some more detail in the following sections from a configuration point of view.

!!! note "Bank Accounts"
    When you install CiviBanking you will automatically get an additional tab on your **Contact Summary** for **Bank Account**. This is discussed in detail in the [How To Guide](how-to.md).
    
##Settings

In the **Administration Console** you get a **CiviBanking Settings** option in the **CiviContribute** tab as you see in the screenshot below:

![Screenshot](/img/admin_console.png)

If you select this option you will see the CiviBanking Settings:

![Screenshot](/img/civibanking_settings.png)

For this documentation section we assume you have accepted all the defaults in the CiviBanking Settings! There will be more detail about the CiviBanking settings in the [How to Guide](how-to.md).

##Importing
As explained, **importing** is about translating the data provided by the payment processor (bank files, csv files etc.) into the CiviBanking speak.

There can be many files containing payments that you would like to have processed like:

* the payment transactions in your bank account that you can get from the bank
* credit card payments that you get from the credit card company
* PayPal payments which you can download from the PayPal website
* SMS payments that you can get from your SMS provider
* etc. etc.

In this section we will discuss how to configure CiviBanking for two types of files:

* the payments transactions you get from the bank in CAMT53 format (a format used by a lot of Western European Banks)
* a payments file in CSV format.

!!! warning
    Unfortunately different banks have slightly different formats which makes it complicated to give one example of a configuration that will work. In my example installation using the standard CAMT053 format works, but if I try to process a file from some specific banks it does not work.
    
    If you get a CAMT053 file from your bank and you find it that you can not process (you get an error _File rejected by importer!_) the best option is to contact an expert that can help you in adapting the importer for the specific format of your bank!
    
### CAMT53 file
Each importer is a so called _plugin_ and can be installed or imported from **Banking>Configuration Manager**. The first time you access the Configuration Manager you will probably get a form like this:

![Screenshot](/img/config_manager_empty.png)

In this case we are interested in adding a _plugin_ to import, so the top part. As you can see there are no plugins configured just yet, so I will click the **Add a new one** link in the **Import plugins** section of the form.

Alternatively, if I have a configuration (including importers, matchers and so on) for CiviBanking from another CiviCRM installation, I can use the **IMPORTER** link to select an exported configuration file and import that into my new CiviBanking installation.
For this section we will assume you are going to create a new one, so I have clicked on the **add a new one** link.

In the next form I enter a _name_ for the plugin, I select the **Import Plugin** as the _class_. As CAMT53 is a type of XML file I select **Configurable XML importer** as _Implementation_. And I enter a few sentences describing what the imported does at _description_. The result will be in the top half of the Configuration Manager Add Plugin screen and will look something like this screenshot:

![Screenshot](/img/camt53_plugin_top.png)

In the bottom half I have to enter the technical information required to interpret the incoming file and know which field in the incoming file to send to which field in the CiviBanking transaction.

This kind of information is entered in [JSON](https://www.json.org/). For the CAMT53 we have an example configuration (from a project where this worked for their BNP Paribas Fortis bank file) which might well (and really should) work for your bank files too. 

You can copy the JSON data below and paste it in the bottom half of the Configuration Manager Add Plugin form (the part marked with **Configuration**). 
Once all the data is entered press the **Save** button to save your plugin configuration. 

If you want to know more about the configuration of an importer and how you can create your specific importer please check [How to create an importer](create-importer.md).

``` json
{
  "comment": "CAMT.53 Import configuration (BNP Paribas Fortis)",
  "defaults": {
    "payment_instrument_id": "4" 
  },
  "namespaces": {
    "camt": "urn:iso:std:iso:20022:tech:xsd:camt.053.001.02" 
  },
  "probe": "camt:BkToCstmrStmt/camt:GrpHdr/camt:MsgId",
  "rules": [
    {
      "comment": "statement name is MsgId/LglSeqNb",
      "from": "xpath:camt:BkToCstmrStmt/camt:GrpHdr/camt:MsgId",
      "to": "tx_batch.reference",
      "type": "set" 
    },
    {
      "comment": "IBAN preset for payments",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Acct/camt:Id/camt:IBAN",
      "to": "tx._IBAN",
      "type": "set" 
    },
    {
      "comment": "BIC preset for payments",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Acct/camt:Svcr/camt:FinInstnId/camt:BIC",
      "to": "tx._BIC",
      "type": "set" 
    },
    {
      "comment": "starting time",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[2]/camt:Dt/camt:Dt",
      "to": "tx_batch.starting_date",
      "type": "strtotime" 
    },
    {
      "comment": "ending time",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[2]/camt:Dt/camt:Dt",
      "to": "tx_batch.ending_date",
      "type": "strtotime" 
    },
    {
      "comment": "statement currency",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[1]/camt:Amt/@Ccy",
      "to": "tx_batch.currency",
      "type": "set" 
    },
    {
      "comment": "statement starting balance. FIXME: include condition instead of position",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[2]/camt:Amt",
      "to": "tx_batch.starting_balance",
      "type": "amount" 
    },
    {
      "comment": "statement starting balance. FIXME: include condition instead of position",
      "from": "xpath:camt:BkToCstmrStmt/camt:Stmt/camt:Bal[1]/camt:Amt",
      "to": "tx_batch.ending_balance",
      "type": "amount" 
    }
  ],
  "payment_lines": [
    {
      "comment": "general lines will be imported per-entry",
      "path": "camt:BkToCstmrStmt/camt:Stmt/camt:Ntry",
      "filter": "not_exists:camt:NtryDtls/camt:Btch",
      "rules": [
        {
          "comment": "booking date",
          "from": "xpath:camt:BookgDt/camt:Dt",
          "to": "booking_date",
          "type": "strtotime" 
        },
        {
          "comment": "value date (AI asked us to use the booking date for both)",
          "from": "xpath:camt:BookgDt/camt:Dt",
          "to": "value_date",
          "type": "strtotime" 
        },
        {
          "comment": "Amount debit/credit",
          "from": "xpath:camt:CdtDbtInd",
          "to": "amount",
          "type": "set" 
        },
        {
          "comment": "Amount",
          "from": "xpath:camt:Amt",
          "to": "amount",
          "type": "append:" 
        },
        {
          "comment": "CRDT means positive (omit +)",
          "to": "amount",
          "from": "amount",
          "type": "replace:CRDT:" 
        },
        {
          "comment": "DBIT means negative",
          "from": "amount",
          "to": "amount",
          "type": "replace:DBIT:-" 
        },
        {
          "comment": "Currency",
          "from": "xpath:camt:Amt/@Ccy",
          "to": "currency",
          "type": "set" 
        },
        {
          "comment": "party IBAN",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:CdtrAcct/camt:Id/camt:IBAN|camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:DbtrAcct/camt:Id/camt:IBAN",
          "to": "_party_IBAN",
          "type": "set" 
        },
        {
          "comment": "party BIC",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdAgts/camt:CdtrAgt/camt:FinInstnId/camt:BIC|camt:NtryDtls/camt:TxDtls/camt:RltdAgts/camt:DbtrAgt/camt:FinInstnId/camt:BIC",
          "to": "_party_BIC",
          "type": "set" 
        },
        {
          "comment": "transaction message",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RmtInf/camt:Ustrd",
          "to": "purpose",
          "type": "set" 
        },
        {
          "comment": "party name",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Cdtr/camt:Nm|camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Dbtr/camt:Nm",
          "to": "name",
          "type": "set" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[1]|camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[1]",
          "to": "street_address",
          "type": "set" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]|camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]",
          "to": "postal_code",
          "type": "regex:#^(\\d{4}\\s+\\w{2}) +\\w+#" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]|camt:NtryDtls/camt:TxDtls/camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]",
          "to": "city",
          "type": "regex:#^\\d{4}\\s+\\w{2} +(\\w.+) *$#" 
        }
      ]
    },
    {
      "comment": "batch entries will be expanded",
      "path": "camt:BkToCstmrStmt/camt:Stmt/camt:Ntry/camt:NtryDtls/camt:TxDtls",
      "filter": "exists:../camt:Btch",
      "rules": [
        {
          "comment": "booking date",
          "from": "xpath:../../camt:BookgDt/camt:Dt",
          "to": "booking_date",
          "type": "strtotime" 
        },
        {
          "comment": "value date (AI asked us to use the booking date for both)",
          "from": "xpath:../../camt:BookgDt/camt:Dt",
          "to": "value_date",
          "type": "strtotime" 
        },
        {
          "comment": "Amount debit/credit",
          "from": "xpath:../../camt:CdtDbtInd",
          "to": "amount",
          "type": "set" 
        },
        {
          "comment": "parse/normalise amount",
          "from": "xpath:camt:AmtDtls/camt:TxAmt/camt:Amt",
          "to": "amount_parsed",
          "type": "amount" 
        },
        {
          "comment": "append parsed amount",
          "from": "amount_parsed",
          "to": "amount",
          "type": "append:" 
        },
        {
          "comment": "CRDT means positive (omit +)",
          "to": "amount",
          "from": "amount",
          "type": "replace:CRDT:" 
        },
        {
          "comment": "DBIT means negative",
          "from": "amount",
          "to": "amount",
          "type": "replace:DBIT:-" 
        },
        {
          "comment": "Currency",
          "from": "xpath:camt:AmtDtls/camt:TxAmt/camt:Amt/@Ccy",
          "to": "currency",
          "type": "set" 
        },
        {
          "comment": "party IBAN",
          "from": "xpath:camt:RltdPties/camt:DbtrAcct/camt:Id/camt:IBAN|camt:RltdPties/camt:CdtrAcct/camt:Id/camt:IBAN",
          "to": "_party_IBAN",
          "type": "set" 
        },
        {
          "comment": "party BIC",
          "from": "xpath:camt:RltdAgts/camt:DbtrAgt/camt:FinInstnId/camt:BIC|RltdAgts/camt:CdtrAgt/camt:FinInstnId/camt:BIC",
          "to": "_party_BIC",
          "type": "set" 
        },
        {
          "comment": "transaction message",
          "from": "xpath:camt:AddtlTxInf",
          "to": "purpose",
          "type": "set" 
        },
        {
          "comment": "party name",
          "from": "xpath:camt:RltdPties/camt:Cdtr/camt:Nm",
          "to": "name",
          "type": "set" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[1]|camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[1]",
          "to": "street_address",
          "type": "set" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]|camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]",
          "to": "postal_code",
          "type": "regex:#^(\\d{4}) +\\w+#" 
        },
        {
          "comment": "party address",
          "from": "xpath:camt:RltdPties/camt:Dbtr/camt:PstlAdr/camt:AdrLine[2]|camt:RltdPties/camt:Cdtr/camt:PstlAdr/camt:AdrLine[2]",
          "to": "city",
          "type": "regex:#^\\d{4} +(\\w.+) *$#" 
        },
        {
          "comment": "SEPA mandate reference",
          "from": "xpath:camt:Refs/camt:MndtId",
          "to": "sepa_mandate",
          "type": "set" 
        },
        {
          "comment": "SEPA status code",
          "from": "xpath:camt:RtrInf/camt:Rsn/camt:Cd",
          "to": "sepa_code",
          "type": "set" 
        }
      ]
    }
  ]
}
```

!!! note
    The _payment_instrument_id_ is important and should be configured correctly. This means that the ID should exists in your database and reflect.........

Once you have completed the configuration of your CAMT53 imported you should test if it actually works!

You can do this by importing a file with **Banking/Import Transactions**. Selecting this from the menu will bring up a form like the screenshot:

![Screenshot](/img/import_camt53_transactions.png)

You can see I have select **CAMT53** as the _configuration_. I have also set the _Dry run_ option to **Yes** so it does not actually import the file I am about to select, but just tells me if it _could_ import the file.
I then click on **Browse** (or _Bladeren_ in my Dutch installation) to select the CAMT53 file I want to test with.

!!! note
    If you need to use other formats you can read through the documentation which will probably give you enough information for your next step.