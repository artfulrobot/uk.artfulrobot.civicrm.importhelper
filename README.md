# CSV Import Helper tool

This is a native CiviCRM version of an older Drupal-based tool that has proved
very useful to my clients.

## Installing and Requirements

You can install this in the normal way.

This extension has been written against CiviCRM 4.7 with PHP 5.6 (multibyte
string functions required) and MariaDB 10.0 (MariaDB is a drop-in replacement
for MySQL that I have found to be more performant than MySQL for CiviCRM, a
recent version of MySQL should work just as well), it requires InnoDB and UTF-8
support.

Any problems running against other versions please submit an issue and ideally 
a pull request :-)


## Use

Visit *Contacts menu* » **CSV Import Helper**.

1. First select your CSV file.
   It should start uploading and say so in a tiny status message in the top-right of the screen.

2. Once your file has uploaded it should say Data Uploaded. Go over to the 2nd tab 
   called "Process". Here you can help CiviCRM choose the right contacts for each set of 
   names/emails. You can create new contacts on that process screen, too.

3. Finally, once all your contacts are matched up you can go to the third tab and download a
   new CSV file which will include an `Internal ID` field at the start. You can use *that*
   CSV file in CiviCRM's normal import processes.
   
4. Clean up by deleting the CSV data you uploaded from the tool (button underneath the
   Download one on the third tab.)


