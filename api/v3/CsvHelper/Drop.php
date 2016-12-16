<?php

/**
 * CsvHelper.Drop API specification (optional)
 * Drops all uploaded CSV data.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_csv_helper_Drop_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * CsvHelper.Drop API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_csv_helper_Drop($params) {
  CRM_CsvImportHelper::truncate();
  return civicrm_api3_create_success([], $params, 'CsvHelper', 'drop');
}

