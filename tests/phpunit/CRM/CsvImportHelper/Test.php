<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests the extension.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_CsvImportHelper_Test extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->callback(function() {
        foreach ([
          ['first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma.one@example.com'],
          ['first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma.two@example.com'],
        ] as $_) {
          civicrm_api3('Contact', 'create', $_ + ['contact_type' => 'Individual']);
        }
      }, 'createTestContacts1')
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }


  /**
   * The data is exepcted to parse strictly as Base 64 encoded data.
   *
   * @expectedException CiviCRM_API3_Exception
   * @expectedExceptionMessage Decoding base64 data failed.
   */
  public function testUploadRejectsInvalidDataBase64() {

    $src = 'data:text/csv;base64,&&this is not base64 data.';
    $result = civicrm_api3('CsvHelper', 'upload', ['data' => $src ]);

  }

  /**
   * Checks for headers.
   *
   * @expectedException CiviCRM_API3_Exception
   * @expectedExceptionMessage Expected URL-encoded data but got: not a data: url
   */
  public function testUploadRejectsInvalidDataWrongHeader() {

    $src = 'not a data: url';
    $result = civicrm_api3('CsvHelper', 'upload', ['data' => $src ]);

  }

  /**
   * Test that the upload facility seems to work.
   */
  public function testUploadParse() {
    $n = 4; // how many distinct records in fixture.
    $src = 'data:text/csv;base64,' . base64_encode(file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'fixtures/contacts.csv'));
    $result = civicrm_api3('CsvHelper', 'upload', ['data' => $src ]);
    $this->assertEquals($n, $result['values']['imported']);
    $this->assertEquals(0, $result['values']['skipped']);

    $cache = civicrm_api3('CsvHelper', 'get');
    $this->assertEquals($n, $cache['count']);

    // First contact has multiple matches.
    $row = array_shift($cache['values']);
    $this->assertEquals('Wilma', $row['fname']);
    $this->assertEquals('Flintstone', $row['lname']);
    $this->assertEquals('multiple', $row['state']);
    $this->assertEquals('', $row['contact_id']);
    $this->assertCount(2, $row['resolution']);

    // Second contact should be found automatically.
    $row = array_shift($cache['values']);
    $this->assertEquals('Wilma', $row['fname']);
    $this->assertEquals('Flintstone', $row['lname']);
    $this->assertEquals('found', $row['state']);
    $this->assertGreaterThan(0, $row['contact_id']);
    $this->assertCount(1, $row['resolution']);
    $cid_wilma = $row['contact_id'];

    // Third contact should be no match.
    $row = array_shift($cache['values']);
    $this->assertEquals('Barney', $row['fname']);
    $this->assertEquals('Rubble', $row['lname']);
    $this->assertEquals('impossible', $row['state']);
    $this->assertEquals('', $row['contact_id']);
    $this->assertCount(0, $row['resolution']);

    // Fourth contact should match (email is unique)
    $row = array_shift($cache['values']);
    $this->assertEquals('', $row['fname']);
    $this->assertEquals('', $row['lname']);
    $this->assertEquals('found', $row['state']);
    $this->assertEquals($cid_wilma, $row['contact_id']);
    $this->assertCount(1, $row['resolution']);

  }

  /**
   *
   * Issue https://github.com/artfulrobot/uk.artfulrobot.civicrm.importhelper/issues/8
   */
  public function testUploadHandlesNewLinesInCsvFields() {
    $src = 'data:text/csv;base64,' . base64_encode(file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'fixtures/testUploadHandlesNewLinesInCsvFields.csv'));
    $result = civicrm_api3('CsvHelper', 'upload', ['data' => $src ]);
    $this->assertEquals(1, $result['values']['imported']);
    $this->assertEquals(0, $result['values']['skipped']);

    $cache = civicrm_api3('CsvHelper', 'get');
    $this->assertEquals(1, $cache['count']);

  }
}
