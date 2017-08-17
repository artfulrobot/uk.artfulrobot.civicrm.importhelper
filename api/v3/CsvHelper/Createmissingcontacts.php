<?php

/**
 * CsvHelper.Createmissingcontacts API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_csv_helper_Createmissingcontacts_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
  $spec['id']['description'] = 'If an import cache ID is given only this contact will be created';
}

/**
 * CsvHelper.Createmissingcontacts API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_csv_helper_Createmissingcontacts($params) {
  if (!empty($params['id'])) {
    CRM_CsvImportHelper::createMissingContact($params['id']);
    // Return a single cache record.
    $return_values = CRM_CsvImportHelper::loadCacheRecord($params['id']);
  }
  else {
    CRM_CsvImportHelper::createMissingContacts();
    // Return all the records.
    $return_values = CRM_CsvImportHelper::loadCacheRecords();
  }
  return civicrm_api3_create_success($return_values, $params, 'CsvHelper', 'createmissingcontacts');
}

