<?php

class CRM_CsvImportHelper {
  protected static $titles_regexp;
  /**
   * Process an upload.
   *
   * @param array $params 'data' is a data URL.
   */
  public static function upload($params) {

    if (empty($params['data'])) {
      throw new InvalidArgumentException('No file sent.');
    }
    if (substr($params['data'], 0, 21) != 'data:text/csv;base64,') {
      throw new InvalidArgumentException('Expected data as data:text/csv and base64 encoded.');
    }
    $file = base64_decode(substr($params['data'], 21), TRUE);
    if (empty($file)) {
      throw new InvalidArgumentException('Decoding base64 data failed.');
    }

    // OK, we have a file!
    $enc = mb_detect_encoding($file, 'ISO-5591-1, cp1252, UTF-8', TRUE);
    if ($enc !== 'UTF-8') {
      // default to latin1 if unable to detect
      $enc = $enc ? $enc : 'ISO-8859-1';
      $file = mb_convert_encoding($file, 'UTF-8', $enc);
    }
    // Ensure line endings are \n
    $file = str_replace('\r', '\n', str_replace('\r\n', '\n', $file));
    // Parse into rows.
    $file = str_getcsv($file, "\n");

    // open the file and import the data.
    $header=1;
    $clean_only = FALSE;
    $skipped_blanks = 0;
    $rows = 0;

    // Drop all existing data.
    static::truncate();

    // Insert new data.
    foreach ($file as $_) {
      $line = str_getcsv($_);
      $line = array(
        'contact_id' => 0,
        'title' => trim($line[0]),
        'fname' => trim($line[1]),
        'lname' => trim($line[2]),
        'email' => trim($line[3]),
        'state' => '',
        'resolution' => [],
        'data' => serialize($line),
      );
      if ("$line[fname]$line[lname]$line[email]" == '') {
        $skipped_blanks++;
        continue;
      }
      if ($header) {
        $line['state'] = 'header';
        $header = 0;
        $rows--; // Don't count the header.
      }
      else {
        if (!$line['lname'] && !$line['title'] && $line['fname']) {
          // Only the 'fname' column has anything in. Try to split a name out from this.
          static::cleanName($line);
        }
        if ($clean_only) {
          $line['state'] = 'clean-only';
        }
        else {
          static::findContact($line);
        }
      }
      $line['resolution'] = serialize($line['resolution']);

      // Insert into table.
      $insertQuery = "INSERT INTO `civicrm_csv_match_cache` (
        `contact_id`, `fname`, `lname`, `email`, `title`,
        `state`, `resolution`, `data`)
        VALUES (%1, %2, %3, %4, %5, %6, %7, %8)";
      $queryParams = [
        1 => [ $line['contact_id'], 'Integer'],
        2 => [ $line['fname'], 'String'],
        3 => [ $line['lname'], 'String'],
        4 => [ $line['email'], 'String'],
        5 => [ $line['title'], 'String'],
        6 => [ $line['state'], 'String'],
        7 => [ $line['resolution'], 'String'],
        8 => [ $line['data'], 'String'],
      ];
      CRM_Core_DAO::executeQuery($insertQuery, $queryParams);
      $rows++;
    }
    if ($skipped_blanks) {
      //drupal_set_message("Warning: the file contained $skipped_blanks blank rows (i.e. did not have name or email). These were ignored, however the job would have been much quicker if they weren't included in the upload :-)", 'warning');
    }

    return ['imported' => $rows, 'skipped' => $skipped_blanks];
  }
  /**
   * This is called in the case that there is something in the first name
   * field, but nothing in last name or title.
   *
   * It attempts to separate the data in first name out into title, first and last name fields.
   */
  public static function cleanName(&$record) {
    $names = trim($record['fname']);
    $titles = static::getTitleRegex();

    if (preg_match('/^([^,]+)\s*,\s*([^,]+)$/', $names, $matches)) {
      // got name in form: Last, First.
      $record['lname'] = $matches[1];
      $record['fname'] = trim($matches[2]);
      if (preg_match("/^($titles)\s+(.+)$/", $record['fname'], $matches)) {
        $record['title'] = $matches[1];
        $record['fname'] = $matches[2];
      }
    }
    else {
      $names = preg_split('/\s+/', $names);
      if (count($names)>1) {
        // prefix?
        if (preg_match("/^$titles$/", $names[0])) {
          $record['title'] = array_shift($names);
        }
        // Let's assume the first word is the first name
        $record['fname'] = array_shift($names);
        $record['lname'] = implode(' ', $names);
      }
    }

    // if all lowercase or all uppercase, then tidy the case.
    foreach (array('fname','lname','title') as $_) {
      $name = $record[$_];
      if (strtolower($name) == $name || strtoupper($name) == $name) {
        $record[$_] = ucfirst($name);
      }
    }
  }
  /**
   * Attempt to find the contact in the database.
   *
   * Returns an array of candidate contacts, keyed by contact id, each line is an array with keys:
   * - contact_id
   * - 'match' why this was a candidate (e.g. email match)
   * - 'name' just the name
   *
   */
  public static function findContact(&$record) {
    $record['resolution'] = [];

    // email is most unique. if we have that, start there.
    if ($record['email']) {

      // got email look it up in the email table
      $result = civicrm_api3('Email', 'get', array( 'sequential' => 1, 'email' =>$record['email']));
      if ($result['count']>0) {
        // We need to join the contact name details onto our email matches array.
        $contact_ids = array();
        foreach ($result['values'] as $_) {
          $contact_ids[$_['contact_id']] = TRUE;
        }
        // Get unique contacts, keyed by contact_id
        $contacts = civicrm_api3('Contact', 'get', [
          'id' => ['IN' => array_keys($contact_ids)],
          'sequential' => 0,
        ]);

        // Make list of unique candidate contacts.
        foreach ($contacts['values'] as $contact_id => $contact) {
          $record['resolution'][] = [
            'contact_id' => (string) $contact_id,
            'match' => ts('Same email'),
            'name'  => $contact['display_name'],
          ];
        }

        if (count($record['resolution']) == 1) {
          // Single winner.
          $record['contact_id'] = (string) $contact_id;
          $record['state'] = 'found';
          return;
        }

        // More than one contact matched.
        // quick scan to see if there's only one that matches first name
        $m = array_filter($record['resolution'], function ($_, $contact_id) use ($contacts, $record) {
          $contact = $contacts[$contact_id];
          return ($contact['first_name'] && $record['fname'] && $contact['first_name'] == $record['fname']);
        });
        if (count($m) == 1) {
          // Only one of these matches on (email and) first name, use that.
          $record['contact_id'] = (string) key($m);
          $record['state'] = 'found';
        }

        // quick scan to see if there's only one that matches last name
        $m = array_filter($record['resolution'], function ($_, $contact_id) use ($contacts, $record) {
          $contact = $contacts[$contact_id];
          return ($contact['last_name'] && $record['lname'] && $contact['last_name'] == $record['lname']);
        });
        if (count($m) == 1) {
          // Only one of these matches on (email and) last name, use that.
          $record['contact_id'] = (string) key($m);
          $record['state'] = 'found';
        }

        // Don't look wider than matched emails.
        return;
      }
    }

    // Now left with cases where the email was not found in the database (or
    // not given in input), so names only.

    if ($record['fname'] && $record['lname']) {
      // see if we can find them by name.
      $params = ['sequential' => 1, 'first_name' => $record['fname'], 'last_name' => $record['lname'], 'return' => 'display_name'];
      $result = civicrm_api3('Contact', 'get', $params);
      if ($result['count']==1) {
        // winner
        $record['contact_id'] = (string) $result['values'][0]['contact_id'];
        $record['resolution'] = [[
          'contact_id' => (string) $result['values'][0]['contact_id'],
          'match' => 'Only name match',
          'name' => $result['values'][0]['display_name'],
        ]];
        $record['state'] = 'found';
        return;
      }

      if ($result['count']>1) {
        // could be any of these contacts
        foreach ($result['values'] as $contact) {
          $record['resolution'][] = [
            'contact_id' => (string) $contact['contact_id'],
            'match' => 'Same name',
            'name'  => $contact['display_name'],
          ];
        }
        $record['state'] = 'multiple';
        $record['contact_id'] = 0;
        return;
      }

      // Still not found? OK, probably something weird with the first name.
      // Let's try last name, with first name as a substring match
      // see if we can find them by name.
      $params = ['sequential' => 1,
        'first_name' => '%' . $record['fname'] . '%',
        'last_name' => $record['lname']];
      $result = civicrm_api3('Contact', 'get', $params);
      if ($result['count']==1) {
        // winner
        $record['contact_id'] = (string) $result['values'][0]['contact_id'];
        $record['resolution'] = [[
          'contact_id' => (string) $result['values'][0]['contact_id'],
          'match' => 'Only similar match',
          'name' => $result['values'][0]['display_name'],
        ]];
        $record['state'] = 'found';
        return;
      }

      if ($result['count']>1) {
        // could be any of these contacts
        $record['resolution'] = array();
        foreach ($result['values'] as $contact) {
          $record['resolution'][] = [
            'contact_id' => (string) $contact['contact_id'],
            'match' => 'Similar name',
            'name'  => $contact['display_name'],
          ];
        }
        $record['state'] = 'multiple';
        $record['contact_id'] = 0;
        return;
      }

      // Still not found, let's try first initial.
      $params = [ 'sequential' => 1,
        'first_name' => substr($record['fname'],0,1) . '%',
        'last_name' => $record['lname'],
        'return' => 'display_name'];
      $result = civicrm_api3('Contact', 'get', $params);
      if ($result['count']>0) {
        // Can't assume from the initial, even if just one person.
        // could be any of these contacts
        foreach ($result['values'] as $contact) {
          $record['resolution'][] = [
            'contact_id' => (string) $contact['contact_id'],
            'match' => 'Similar name',
            'name'  => $contact['display_name'],
          ];
        }
        $record['state'] = 'multiple';
        $record['contact_id'] = 0;
        return;
      }
    }

    // OK, maybe the last name is particularly unique?
    if ($record['lname']) {
      $params = ['sequential' => 1, 'last_name' => $record['lname']];
      $result = civicrm_api3('Contact', 'get', $params);
      if ($result['count']>10) {
        $record['state'] = 'multiple';
        $record['contact_id'] = 0;
      }
      elseif ($result['count']>0) {
        // could be any of these contacts
        $record['resolution'] = array();
        foreach ($result['values'] as $contact) {
          $record['resolution'][] = [
            'contact_id' => (string) $contact['contact_id'],
            'match' => 'Same last name',
            'name'  => $contact['display_name'],
          ];
        }
        $record['state'] = 'multiple';
        $record['contact_id'] = 0;
        return;
      }
    }

    // if we're here, we only have one name, so let's not bother.
    $record['state'] = 'impossible';
    $record['contact_id'] = 0;
    $record['resolution'] = [];
  }
  /**
   * Get the regex to identify titles.
   *
   * Currently this just uses some used in UK, ideally it would fetch a list of
   * configured prefixes from CiviCRM. PRs welcome.
   */
  public static function getTitleRegex() {
    if (empty(static::$titles_regexp)) {
      static::$titles_regexp = '(?:Ms|Miss|Mrs|Mr|Dr|Prof|Rev|Cllr|Rt Hon).?';
    }
    return static::$titles_regexp;
  }
  /**
   * Update the civicrm_csv_match_cache table.
   */
  public static function update($record_id, $updates) {

    $record = static::loadCacheRecords(['id' => $record_id]);
    if (count($record) != 1) {
      throw new InvalidArgumentException("Failed to load the record. Try reloading the page.");
    }
    $record = reset($record);

    static::updateSet(
      [
        'fname' => $record['fname'],
        'lname' => $record['lname'],
        'email' => $record['email'],
      ]
      + $updates
    );

    // Reload and return.
    $record = static::loadCacheRecords([
      'fname' => $record['fname'],
      'lname' => $record['lname'],
      'email' => $record['email'],
    ]);
    return reset($record);
  }
  /**
   * Update the civicrm_csv_match_cache table.
   */
  public static function updateSet($updates) {

    // Update all records for the same name and email.
    $sql = "UPDATE `civicrm_csv_match_cache`
      SET state = %1, contact_id = %2 "
      . (isset($updates['resolution']) ? ', resolution = %6 ' : '')
      . "WHERE fname = %3 AND lname = %4 AND email = %5";

    $queryParams = [
      1 => [ $updates['state'], 'String'],
      2 => [ $updates['contact_id'], 'Integer'],
      3 => [ $updates['fname'], 'String' ],
      4 => [ $updates['lname'], 'String' ],
      5 => [ $updates['email'], 'String' ],
    ];
    if (isset($updates['resolution'])) {
      $queryParams[6] = [ $updates['resolution'], 'String' ];
    }
    $result = CRM_Core_DAO::executeQuery($sql, $queryParams);
  }
    public static function csvSafe($string) {
      return '"' . str_replace('"','""',$string) . '"';
    }
  /**
   * Load rows from civicrm_csv_match_cache.
   *
   * @param array $filters with the following optional keys:
   * - id
   * - fname
   * - lname
   * - email
   *
   * @return array of records.
   */
  public static function loadCacheRecords($filters=[]) {

    $params = [];
    $wheres = [];
    $i = 1;

    if (isset($filters['id'])) {
      $wheres []= "id = %$i";
      $params[$i++] = [ $filters['id'], 'Integer' ];
    }

    foreach (['fname', 'lname', 'email'] as $_) {
      if (isset($filters[$_])) {
        $wheres []= "$_ = %$i";
        $params[$i++] = [ $filters[$_], 'String' ];
      }
    }

    $wheres = $wheres ? ('AND ' . implode(' AND ', $wheres)) : '';

    // Select every column except 'data'. Keep original input order (id)
    $sql = "
      SELECT id, contact_id, fname, lname, email, title, state, resolution, COUNT(id) set_count FROM civicrm_csv_match_cache
      WHERE state != 'header' $wheres
      GROUP BY fname, lname, email
      ORDER BY id
    ";
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $return_values = $dao->fetchAll();
    $dao->free();

    // Unpack the resolution field, stored serialize()-ed.
    foreach ($return_values as &$row) {
      $row['resolution'] = $row['resolution'] ? unserialize($row['resolution']) : [];
    }

    return $return_values;
  }
  /**
   * Spit a CSV file out.
   */
  public static function spewCsv() {
    // Select everything except 'data'.
    $sql = "
      SELECT contact_id, title, fname, lname, data FROM civicrm_csv_match_cache
      ORDER BY id
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $dao->fetch();

    // Add the headers needed to let the browser know this is a csv file download.
    header('Content-Type: text/csv; utf-8');
    header('Content-Disposition: attachment; filename = civicrm-import-data.csv');

    // Output Headers for CSV:
    //
    // prepend contact ID and new name fields - just so we're not overwriting the old data.
    print '"Internal ID","Title", "First Name", "Last Name"';
    $data = unserialize($dao->data);
    // unpack original header line
    $data[0] = "Orig: $data[0]";
    $data[1] = "Orig: $data[1]";
    $data[2] = "Orig: $data[2]";
    foreach ($data as $_) {
      print "," . static::csvSafe($_);
    }
    print "\n";

    // Now output rest of data.
    while ($dao->fetch()) {
      $data = unserialize($dao->data);
      // prepend contact ID and name fields
      print ($dao->contact_id ? $dao->contact_id : '""');
      print "," . static::csvSafe($dao->title);
      print "," . static::csvSafe($dao->fname);
      print "," . static::csvSafe($dao->lname);
      foreach ($data as $_) {
        print "," . static::csvSafe($_);
      }
      print "\n";
    }
    exit;
  }
  /**
   * Spit a CSV file out.
   */
  public static function truncate() {
    CRM_Core_DAO::executeQuery('TRUNCATE civicrm_csv_match_cache;');
  }
  /**
   * Rescan all un-selected contacts.
   */
  public static function rescan() {

    // Select everything except 'data'.
    $sql = "
      SELECT MIN(id) id, title, fname, lname, email FROM civicrm_csv_match_cache
      WHERE contact_id = 0 AND state != 'header'
      GROUP BY fname, lname, email
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $line = [
        'fname' => $dao->fname,
        'lname' => $dao->lname,
        'email' => $dao->email,
      ];
      static::findContact($line);
      $line['resolution'] = serialize($line['resolution']);
      static::updateSet($line);
    }

  }
  /**
   * Rescan all un-selected contacts.
   */
  public static function createMissingContacts() {

    $sql = "
      SELECT MIN(id) id, title, fname, lname, email FROM civicrm_csv_match_cache
      WHERE contact_id = 0 AND state = 'impossible'
      GROUP BY fname, lname, email
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $params = [
        'contact_type' => 'Individual',
        'first_name'   => $dao->fname,
        'last_name'    => $dao->lname,
      ];

      $contact = civicrm_api3('Contact', 'create', $params);

      if ($dao->email) {
        $params = [
          'contact_id' => $contact['id'],
          'email'      => $dao->email,
        ];
        $email = civicrm_api3('Email', 'create', $params);
      }

      // Now update record.
      static::updateSet([
        'fname' => $dao->fname,
        'lname' => $dao->lname,
        'email' => $dao->email,
        'state' => 'found',
        'contact_id' => $contact['id'],
        'resolution' => serialize(
          [[
            'contact_id' => (string) $contact['id'],
            'match' => ts('Created'),
            'name'  => "$dao->lname, $dao->fname",
          ]]),
      ]);
    }

    $dao->free();

  }
  static function getSummary($counts = null) {
    // Summarise data

    if ($counts === null) {
      $rows = db_query("
        SELECT *, COUNT(id) set_count FROM {civicrm_csv_match_cache} todo
        WHERE state != 'header'
        GROUP BY fname, lname, email");

      $counts = array('impossible' => 0, 'multiple' => 0,'chosen'=>0,'found'=>0);
      while($row = $rows->fetchAssoc()) {
        switch($row['state']) {
        case 'chosen':
          if ($row['contact_id']) {
            $counts['chosen']++;
          } else {
            $counts['impossible']++;
          }
          break;
        default:
          $counts[$row['state']]++;
        }
      }
    }

    return "<div id='csv-match-summary'><h2>Data</h2><p>Here is the data that you uploaded. "
    . ($counts['found']>0 ? "$counts[found] contact(s) were automatically matched. " : "")
    . ($counts['chosen']>0 ? "$counts[chosen] ambiguous match(es) have been resolved by you. " : "")
    . ($counts['multiple']>0 ? "$counts[multiple] contact(s) could not be automatically matched because the data
   is ambiguous, e.g. two contacts with same email or name. With these you should choose from the possibilities below. " : "")
    . ($counts['impossible']>0 ? "<p>There are $counts[impossible] contacts below for which no contact record could be found. You can <a href='/civicrm/csvmatch/create' >create contact records for them now</a> if you like. You won't be able to import contributions (activities etc.) until these contacts do exist.</p>" : "")
    . ($counts['impossible'] == 0 && $counts['multiple'] == 0 ? "<p><strong>All the rows have a contact match so this dataset looks ready for you to download now.</strong></p>" : "")
    . '</div>'
    ;
  }
}
