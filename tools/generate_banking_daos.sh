#!/bin/bash

extensionFolder="../../../default/files/extensions"
extensionName="CiviBanking"
folderpath=`pwd`
foldername=`basename $folderpath`

if [[ "$foldername" != "civicrm" ]]; then
	echo "This has to be executed from the civicrm base folder, e.g. /var/www/drupal/sites/all/modules/civicrm"
	exit
fi


echo "linking BankingSchema.xml..."
ln -s ../$extensionFolder/$extensionName/xml/schema/BankingSchema.xml xml/BankingSchema.xml

echo "extending xml/schema/Schema.xml..."
cp xml/schema/Schema.xml xml/schema/Schema.xml_backup
sed '/^<\/database>/ i\
<xi:include href="Banking/files.xml" parse="xml" />
' xml/schema/Schema.xml_backup > xml/schema/Schema.xml

echo "running GenCode"
cd xml
php GenCode.php
echo "done."
echo
cd ..

echo "copying generated DAOs to extension..."
cp -rpv CRM/Banking/DAO $extensionFolder/$extensionName/CRM
echo "done."
echo

echo "removing Banking items them from your core..."
rm -rv CRM/Banking
echo "done."
echo

echo "restoring original xml/schema/Schema.xml..."
mv xml/schema/Schema.xml_backup xml/schema/Schema.xml
echo "done."
echo

echo "unlinking BankingSchema.xml..."
rm xml/BankingSchema.xml
echo "done."
echo
echo "finished."
