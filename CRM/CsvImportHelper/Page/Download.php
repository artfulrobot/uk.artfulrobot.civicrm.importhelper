<?php

require_once 'CRM/Core/Page.php';

class CRM_CsvImportHelper_Page_Download extends CRM_Core_Page {
  public function run() {
    // This causes an abrupt exit().
    CRM_CsvImportHelper::spewCsv();
  }
}
