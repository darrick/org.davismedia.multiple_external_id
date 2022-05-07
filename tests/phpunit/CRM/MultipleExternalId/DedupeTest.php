<?php

use CRM_MultipleExternalId_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

/**
 * FIXME - Add test description.
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
class CRM_MultipleExternalId_DedupeTest extends  \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  /**
   * IDs of created contacts.
   *
   * @var array
   */
  protected $contactIDs = [];

  /**
   * ID of the group holding the contacts.
   *
   * @var int
   */
  protected $groupID;
  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
    parent::tearDown();
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testWellFormedVersion():void {
    $this->assertNotEmpty(E::SHORT_NAME);
    $this->assertRegExp('/^([0-9\.]|alpha|beta)*$/', \CRM_Utils_System::version());
  }

  /**
   * Example: Test that we're using a fake CMS.
   */
  public function testWellFormedUF():void {
    $this->assertEquals('UnitTests', CIVICRM_UF);
  }

  /**
   * Test rule from Richard
   *
   * @throws \CRM_Core_Exception
   */
  public function testRuleMultipleIdDupe() {
    $this->setupForGroupDedupe();

    $ruleGroup = $this->callAPISuccess('RuleGroup', 'create', [
      'contact_type' => 'Individual',
      'threshold' => 10,
      'used' => 'General',
      'name' => 'TestRule',
      'title' => 'TestRule',
      'is_reserved' => 0,
    ]);

    foreach (['multiple_external_id'] as $field) {
      $rules[$field] = $this->callAPISuccess('Rule', 'create', [
        'dedupe_rule_group_id' => $ruleGroup['id'],
        'rule_weight' => 10,
        'rule_field' => $field,
      ]);
    }
    $foundDupes = CRM_Dedupe_Finder::dupesInGroup($ruleGroup['id'], $this->groupID);
    $this->assertCount(1, $foundDupes);
  }

  /**
   * Set up a group of dedupable contacts.
   *
   * @throws \CRM_Core_Exception
   */
  protected function setupForGroupDedupe() {
    $params = [
      'name' => 'Dupe Group',
      'title' => 'New Test Dupe Group',
      'domain_id' => 1,
      'is_active' => 1,
      'visibility' => 'Public Pages',
    ];

    $result = $this->callAPISuccess('group', 'create', $params);
    $this->groupID = $result['id'];

    $params = [
      [
        'first_name' => 'robin',
        'last_name' => 'hood',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => 'duplicate',
          ],
      ],
      [
        'first_name' => 'bob',
        'last_name' => 'dobbs',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => 'duplicate',
          ],
      ],
    ];

    $count = 1;
    foreach ($params as $param) {
      $contact = $this->callAPISuccess('contact', 'create', $param);
      $this->contactIDs[$count++] = $contact['id'];

      $grpParams = [
        'contact_id' => $contact['id'],
        'group_id' => $this->groupID,
      ];
      $this->callAPISuccess('group_contact', 'create', $grpParams);
    }

    // verify that all contacts have been created separately
    $this->assertEquals(count($this->contactIDs), 2, 'Check for number of contacts.');


  }
}
