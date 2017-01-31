# CiviCRM CSV Import Helper

## What problems does this help with?

- You have a load of data to import as **contributions** or **activities**
  (etc.) but because it came from an external source it doesn't have
  CiviCRM Contact IDs in it.

- You have contributions/activities to import for contacts that might not be in
  your database yet.

- You have data to import to **contacts** but it's a bit of a mess and may
  result in duplicates, even despite your expertise in using CiviCRM's dedupe
  rules.

- You have data to import but the first and last names are just in one 'name'
  field instead separate fields.

## How does it help?

You feed it a CSV (Comma-Separated Values) spreadsheet and it uses an
interactive process to help *you* identify the contacts for each row. You can
also have it create contacts when you have decided they need to be.

It tries to be helpful by looking for matches for you, based on name and email.
If it finds a single matching contact it will auto-select that, otherwise it
will offer you options, e.g. "Same email: Wilma Flintstone", or "Similar name:
Betty Rubble".

Also it only shows you distinct sets of first, last names and emails. So if your
data has a bunch of records from Wilma Flintstone, you'll only have to locate
Wilma once.

When everything is matched up, you can download the CSV file again, which will
be the same file as you fed into it, but now it has an extra "Internal ID"
column with the CiviCRM Contact ID populated.

**Then** you can use that in CiviCRM's normal import routines for contacts,
activities, contributions...

## Sound good?

See [Installation](installation.md) and then jump across to [Usage](usage.md)

## Artful Robot wrote this

That's me. I'm a UK based developer stitching together open source technologies
to make websites and databases that help people and organisations change the world.

You can hire me if you like :-) My website's [artfulrobot.uk](https://artfulrobot.uk)
