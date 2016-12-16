<?php

/**
 * CsvHelper.Upload API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_csv_helper_Upload_spec(&$spec) {
  $spec['data']['api.required'] = 1;
}

/**
 * CsvHelper.Upload API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_csv_helper_Upload($params) {
  if (empty($params['data'])) {
    throw new API_Exception('No file sent.');
  }
  if (substr($params['data'], 0, 21) != 'data:text/csv;base64,') {
    throw new API_Exception('Expected data as data:text/csv and base64 encoded.');
  }
  $file = base64_decode(substr($params['data'], 21));
  if (empty($file)) {
    throw new API_Exception('Decoding base64 data failed.');
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
  CRM_Core_DAO::executeQuery('TRUNCATE civicrm_csv_match_cache;');
  foreach ($file as $_) {
    $line = str_getcsv($_);
    $line = array(
      'contact_id' => 0,
      'title' => trim($line[0]),
      'fname' => trim($line[1]),
      'lname' => trim($line[2]),
      'email' => trim($line[3]),
      'state' => '',
      'resolution' => '',
      'data' => serialize($line),
    );
    if ("$line[fname]$line[lname]$line[email]" == '') {
      $skipped_blanks++;
      continue;
    }
    if ($header) {
      $line['state'] = 'header';
      $header = 0;
    }
    else {
      if (!$line['lname'] && !$line['title'] && $line['fname']) {
        CRM_CsvImportHelper::cleanName($line);
      }
      if ($clean_only) {
        $line['resolution'] = '(clean only mode)';
        $line['state'] = 'clean-only';
      }
      else {
        CRM_CsvImportHelper::findContact($line);
      }
      $line['resolution'] = serialize($line['resolution']);
    }

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

  return civicrm_api3_create_success(['imported' => $rows, 'skipped' => $skipped_blanks], $params, 'CsvHelper', 'upload');
}

