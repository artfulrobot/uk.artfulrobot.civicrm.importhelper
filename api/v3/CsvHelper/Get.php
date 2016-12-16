<?php

/**
 * CsvHelper.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_csv_helper_Get_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * CsvHelper.Get API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_csv_helper_Get($params) {

  // Q. how to check permission? todo
  $return_values = CRM_CsvImportHelper::loadCacheRecords();
  return civicrm_api3_create_success($return_values, $params, 'CsvHelper', 'get');
}

