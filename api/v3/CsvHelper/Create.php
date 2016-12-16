<?php

/**
 * CsvHelper.Create API specification (optional)
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_csv_helper_Create_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * CsvHelper.Create API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_csv_helper_Create($params) {

  // @todo security
  $record_id = (int) (isset($params['id']) ? $params['id'] : 0);
  $contact_id = (int) (isset($params['contact_id']) ? $params['contact_id'] : 0);
  if ($record_id < 1) {
    throw new API_Exception("Invalid CSV record ID.");
  }
  if (!isset($params['state']) || !in_array($params['state'], [
    'chosen', 'multiple'
  ])) {
    throw new API_Exception("Invalid 'state' value.");
  }
  if ($contact_id < 0) {
    throw new API_Exception("Invalid contact ID.");
  }
  elseif ($contact_id == 0 && $params['state'] == 'chosen') {
    throw new API_Exception("Invalid chosen contact ID.");
  }

  try {
    $result = CRM_CsvImportHelper::update($record_id, [
      'contact_id' => $contact_id,
      'state' => $params['state'],
    ]);
  }
  catch (\Exception $e) {
    throw new API_Exception($e->getMessage());
  }
  return civicrm_api3_create_success($result, $params, 'CsvHelper', 'create');
}

