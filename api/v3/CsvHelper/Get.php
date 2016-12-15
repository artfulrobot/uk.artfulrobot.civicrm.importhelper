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


  $dao = CRM_Core_DAO::executeQuery("SELECT id, contact_id, fname, lname, email, title, state, resolution FROM civicrm_csv_match_cache ORDER BY state, email, lname, fname");
  $returnValues = $dao->fetchAll();
  $dao->free();
  // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
  return civicrm_api3_create_success($returnValues, $params, 'CsvHelper', 'get');
  // ALTERNATIVE: $returnValues = array(); // OK, success
  // ALTERNATIVE: $returnValues = array("Some value"); // OK, return a single value

  throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
}

