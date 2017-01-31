# Installing and Requirements

## Requirements

This extension has been written against CiviCRM 4.7 with PHP 5.6 (multibyte
string functions required) and MariaDB 10.0 (MariaDB is a drop-in replacement
for MySQL that I have found to be more performant than MySQL for CiviCRM, a
recent version of MySQL should work just as well), it requires InnoDB and UTF-8
support.

I have also found it to work with 4.6.24.

I have used it in Drupal and Wordpress setups.

Any problems running against other versions please [submit an
issue](https://github.com/artfulrobot/uk.artfulrobot.civicrm.importhelper/issues) and ideally
a pull request :-)


## Download and install the extension

You can install this extension in the normal way.

It's now listed on the CiviCRM Extensions directory: [CSV Import
Helper](https://civicrm.org/extensions/csv-import-helper) but until this makes
it onto your own database's extensions page, this means grab the latest release code from the
github repo, at
<https://github.com/artfulrobot/uk.artfulrobot.civicrm.importhelper/releases>

From that page you can either download a ZIP/tgz file, or clone the repo if
you're familiar with Git.

Anwyay, you want to end up with a directory called
`uk.artfulrobot.civicrm.importhelper` within your CiviCRM extensions directory.

Then visit *CiviCRM » Administer » System Settings » Extensions* and install it.

