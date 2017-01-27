# Installing and Requirements

## Requirements

This extension has been written against CiviCRM 4.7 with PHP 5.6 (multibyte
string functions required) and MariaDB 10.0 (MariaDB is a drop-in replacement
for MySQL that I have found to be more performant than MySQL for CiviCRM, a
recent version of MySQL should work just as well), it requires InnoDB and UTF-8
support.

I have also found it to work with 4.6.24.

Any problems running against other versions please submit an issue and ideally
a pull request :-)


## Download and install the extension

You can install this extension in the normal way. Until this makes it into the
CiviCRM extensions directory, this means grab the code from its github repo, at
<https://github.com/artfulrobot/uk.artfulrobot.civicrm.importhelper>

From that page you can either download a ZIP file (look under the Releases tab),
or clone the repo if you're familiar with Git.

Anwyay, you want to end up with a directory called
`uk.artfulrobot.civicrm.importhelper` within your CiviCRM extensions directory.

Then visit CiviCRM » Administer » System Settings » Extensions and install it.

