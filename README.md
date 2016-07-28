CiviBanking
===========

CiviCRM banking extension

Implements handling of bank accounts for contacts, as well as handling of bank files (and individual bank payements extracted from the files). Bank files can be imported, payments matched to CiviCRM entities, and the resulting data exported. Specific handlers for all of these actions are provided through plugins, some of which are shipped with the core banking extension, while some more complex ones are provided in separate extensions.


Documentation
=============

You can find the (preliminary) documentation in the [Project Wiki](https://github.com/Project60/CiviBanking/wiki). If you just want to get an idea of what this is about, we recommend watching the [**session on CiviBanking at CiviCon Amsterdam 2015**](https://vimeo.com/143368850).


Development Installation
========================

Clone Repository and link the 'extension' folder of the CiviBanking project into you CiviCRM extensions folder, e.g. like this:
```
> cd /var/www/drupal/sites/default/files/extensions/
> ln -s ~/Documents/workspace/CiviBanking/extension org.project60.banking
```
