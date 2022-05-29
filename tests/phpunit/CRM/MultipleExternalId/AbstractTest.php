<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

/**
 * Class CRM_Dedupe_DedupeMergerTest
 *
 * @group headless
 */
class CRM_MultipleExternalId_AbstractTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3DocTrait;
  use \Civi\Test\GenericAssertionsTrait;
  use \Civi\Test\DbTestTrait;
  use \Civi\Test\ContactTestTrait;

  protected $_ruleGroupId;

  protected $_groupId;

  protected $_contactIds = [];

  /**
   * Contacts created for the test.
   *
   * Overlaps contactIds....
   *
   * @var array
   */
  protected $contacts = [];

  protected function setUp(): void {
    parent::setUp();

  }

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

  /**
   * Tear down.
   *
   * @throws \CRM_Core_Exception
   */
  public function tearDown(): void {
    foreach ($this->_contactIds as $contactId) {
      $this->contactDelete($contactId);
    }
    if ($this->_groupId) {
      $this->callAPISuccess('group', 'delete', ['id' => $this->_groupId]);
    }
    CRM_Core_DAO::executeQuery("DELETE r FROM civicrm_dedupe_rule_group rg INNER JOIN civicrm_dedupe_rule r ON rg.id = r.dedupe_rule_group_id WHERE rg.is_reserved = 0 AND used = 'General'");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_dedupe_rule_group WHERE is_reserved = 0 AND used = 'General'");
    parent::tearDown();
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function createDupeContacts(): void {
    // create a group to hold contacts, so that dupe checks don't consider any other contacts in the DB
    $params = [
      'name' => 'Test Dupe Merger Group',
      'title' => 'Test Dupe Merger Group',
      'domain_id' => 1,
      'is_active' => 1,
      'visibility' => 'Public Pages',
    ];

    $result = $this->callAPISuccess('group', 'create', $params);
    $this->_groupId = $result['id'];

    // contact data set

    // make dupe checks based on based on following contact sets:
    // FIRST - LAST - EMAIL
    // ---------------------------------
    // robin  - hood - robin@example.com
    // robin  - hood - robin@example.com
    // robin  - hood - hood@example.com
    // robin  - dale - robin@example.com
    // little - dale - dale@example.com
    // little - dale - dale@example.com
    // will   - dale - dale@example.com
    // will   - dale - will@example.com
    // will   - dale - will@example.com
    $params = [
      [
        'first_name' => 'robin',
        'last_name' => 'hood',
        'email' => 'robin@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '1',
        ],
      ],
      [
        'first_name' => 'robin',
        'last_name' => 'hood',
        'email' => 'robin@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '1',
        ],
      ],
      [
        'first_name' => 'robin',
        'last_name' => 'hood',
        'email' => 'hood@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '2',
        ],
      ],
      [
        'first_name' => 'robin',
        'last_name' => 'dale',
        'email' => 'robin@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '3',
        ],
      ],
      [
        'first_name' => 'little',
        'last_name' => 'dale',
        'email' => 'dale@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '4',
        ],
      ],
      [
        'first_name' => 'little',
        'last_name' => 'dale',
        'email' => 'dale@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '4',
        ],
      ],
      [
        'first_name' => 'will',
        'last_name' => 'dale',
        'email' => 'dale@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '5',
        ],
      ],
      [
        'first_name' => 'will',
        'last_name' => 'dale',
        'email' => 'will@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '6',
        ],
      ],
      [
        'first_name' => 'will',
        'last_name' => 'dale',
        'email' => 'will@example.com',
        'contact_type' => 'Individual',
        'api.ExternalId.create' => [
          'external_id' => '6',
        ],
      ],
    ];

    $count = 1;
    foreach ($params as $param) {
      $param['version'] = 3;
      $contact = $this->callAPISuccess('contact', 'create', $param);
      $this->_contactIds[$count++] = $contact['id'];

      $grpParams = [
        'contact_id' => $contact['id'],
        'group_id' => $this->_groupId,
        'version' => 3,
      ];
      $this->callAPISuccess('group_contact', 'create', $grpParams);
    }

  }

  /**
   * Delete all created contacts.
   */
  public function deleteDupeContacts(): void {
    foreach ($this->_contactIds as $contactId) {
      $this->contactDelete($contactId);
    }
    $this->groupDelete($this->_groupId);
  }

  public function createRuleGroup(): void {
    $ruleGroup = $this->callAPISuccess('RuleGroup', 'create', [
      'contact_type' => 'Individual',
      'threshold' => 10,
      'used' => 'General',
      'name' => 'TestRule',
      'title' => 'TestRule',
      'is_reserved' => 0,
    ]);

    foreach (['external_id'] as $field) {
      $rules[$field] = $this->callAPISuccess('Rule', 'create', [
        'dedupe_rule_group_id' => $ruleGroup['id'],
        'rule_weight' => 10,
        'rule_field' => $field,
        'rule_table' => 'civicrm_external_id',
      ]);
    }

    $this->_ruleGroupId = $ruleGroup['id'];
  }

}
