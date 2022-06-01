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
class CRM_MultipleExternalId_DedupeFinderTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
    $this->cleanup();
    parent::tearDown();
  }

  /**
   * Tear down.
   *
   * @throws \CRM_Core_Exception
   */
  public function cleanup(): void {
    foreach ($this->_contactIds as $contactId) {
      $this->contactDelete($contactId);
    }
    if ($this->_groupId) {
      $this->callAPISuccess('group', 'delete', ['id' => $this->_groupId]);
    }
    CRM_Core_DAO::executeQuery("DELETE r FROM civicrm_dedupe_rule_group rg INNER JOIN civicrm_dedupe_rule r ON rg.id = r.dedupe_rule_group_id WHERE rg.is_reserved = 0 AND used = 'General'");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_dedupe_rule_group WHERE is_reserved = 0 AND used = 'General'");
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

  /**
   * Test dupesByParams function.
   *
   * Test that external_identifier matches via hook_findDuplicates.
   *
   * @throws \CRM_Core_Exception
   */
  public function testDupesByParams() {

    $this->createDupeContacts();

    $fields = [
      'first_name' => 'bob',
      'last_name' => 'dobbs',
      'email' => 'dobbs@bob.com',
      'external_identifier' => 1,
    ];
    $ids = CRM_Contact_BAO_Contact::getDuplicateContacts($fields, 'Individual', 'General', [], TRUE, NULL, ['event_id' => 1]);

    // Check with default Individual-General rule
    $this->assertEquals(2, count($ids), 'Check Individual-General rule for dupesByParams().');

  }

  /**
   * Test the batch merge.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function testBatchMergeSelectedDuplicates(): void {
    $this->createDupeContacts();
    $this->createRuleGroup();

    // verify that all contacts have been created separately
    $this->assertEquals(count($this->_contactIds), 9, 'Check for number of contacts.');

    $foundDupes = CRM_Dedupe_Finder::dupesInGroup($this->_ruleGroupId, $this->_groupId);

    // -------------------------------------------------------------------------
    // Name and Email (reserved) Matches ( 3 pairs )
    // --------------------------------------------------------------------------
    // robin  - hood - robin@example.com
    // robin  - hood - robin@example.com
    // little - dale - dale@example.com
    // little - dale - dale@example.com
    // will   - dale - will@example.com
    // will   - dale - will@example.com
    // so 3 pairs for - first + last + mail
    $this->assertEquals(count($foundDupes), 3, 'Test dupes found.');

    // Run dedupe finder as the browser would
    //avoid invalid key error
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $object = new CRM_Contact_Page_DedupeFind();
    $object->set('gid', $this->_groupId);
    $object->set('rgid', $this->_ruleGroupId);
    $object->set('action', CRM_Core_Action::UPDATE);
    $object->setEmbedded(TRUE);
    @$object->run();

    // Retrieve pairs from prev next cache table
    $select = ['pn.is_selected' => 'is_selected'];
    $cacheKeyString = CRM_Dedupe_Merger::getMergeCacheKeyString($this->_ruleGroupId, $this->_groupId, [], TRUE, 0);
    $pnDupePairs = CRM_Core_BAO_PrevNextCache::retrieve($cacheKeyString, NULL, NULL, 0, 0, $select);
    $this->assertEquals(count($foundDupes), count($pnDupePairs), 'Check number of dupe pairs in prev next cache.');

    // mark first two pairs as selected
    CRM_Core_DAO::singleValueQuery("UPDATE civicrm_prevnext_cache SET is_selected = 1 WHERE id IN ({$pnDupePairs[0]['prevnext_id']}, {$pnDupePairs[1]['prevnext_id']})");

    $pnDupePairs = CRM_Core_BAO_PrevNextCache::retrieve($cacheKeyString, NULL, NULL, 0, 0, $select);
    $this->assertEquals(1, $pnDupePairs[0]['is_selected'], 'Check if first record in dupe pairs is marked as selected.');
    $this->assertEquals(1, $pnDupePairs[0]['is_selected'], 'Check if second record in dupe pairs is marked as selected.');

    // batch merge selected dupes
    $result = CRM_Dedupe_Merger::batchMerge($this->_ruleGroupId, $this->_groupId, 'safe', 5, 1);
    $this->assertEquals(count($result['merged']), 2, 'Check number of merged pairs.');

    $stats = $this->callAPISuccess('Dedupe', 'getstatistics', [
      'group_id' => $this->_groupId,
      'rule_group_id' => $this->_ruleGroupId,
      'check_permissions' => TRUE,
    ])['values'];
    $this->assertEquals(['merged' => 2, 'skipped' => 0], $stats);

    // retrieve pairs from prev next cache table
    $pnDupePairs = CRM_Core_BAO_PrevNextCache::retrieve($cacheKeyString, NULL, NULL, 0, 0, $select);
    $this->assertEquals(count($pnDupePairs), 1, 'Check number of remaining dupe pairs in prev next cache.');

  }

  /**
   * Test the batch merge.
   */
  public function testBatchMergeAllDuplicates() {
    $this->createDupeContacts();
    $this->createRuleGroup();

    // verify that all contacts have been created separately
    $this->assertEquals(count($this->_contactIds), 9, 'Check for number of contacts.');

    $foundDupes = CRM_Dedupe_Finder::dupesInGroup($this->_ruleGroupId, $this->_groupId);

    // -------------------------------------------------------------------------
    // Name and Email (reserved) Matches ( 3 pairs )
    // --------------------------------------------------------------------------
    // robin  - hood - robin@example.com
    // robin  - hood - robin@example.com
    // little - dale - dale@example.com
    // little - dale - dale@example.com
    // will   - dale - will@example.com
    // will   - dale - will@example.com
    // so 3 pairs for - first + last + mail
    $this->assertEquals(count($foundDupes), 3, 'Check Individual-Supervised dupe rule for dupesInGroup().');

    // Run dedupe finder as the browser would
    //avoid invalid key error
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $object = new CRM_Contact_Page_DedupeFind();
    $object->set('gid', $this->_groupId);
    $object->set('rgid', $this->_ruleGroupId);
    $object->set('action', CRM_Core_Action::UPDATE);
    $object->setEmbedded(TRUE);
    @$object->run();

    // Retrieve pairs from prev next cache table
    $select = ['pn.is_selected' => 'is_selected'];
    $cacheKeyString = CRM_Dedupe_Merger::getMergeCacheKeyString($this->_ruleGroupId, $this->_groupId, [], TRUE, 0);
    $pnDupePairs = CRM_Core_BAO_PrevNextCache::retrieve($cacheKeyString, NULL, NULL, 0, 0, $select);

    $this->assertEquals(count($foundDupes), count($pnDupePairs), 'Check number of dupe pairs in prev next cache.');

    // batch merge all dupes
    $result = CRM_Dedupe_Merger::batchMerge($this->_ruleGroupId, $this->_groupId, 'safe', 5, 2);
    $this->assertEquals(count($result['merged']), 3, 'Check number of merged pairs.');

    foreach ($result['merged'] as $contacts) {
      $contact = $this->callApiSuccess('Contact', 'getsingle', [
        'id' => $contacts['main_id'],
        'api.ExternalId.get' => [],
      ]);
    }

    $stats = $this->callAPISuccess('Dedupe', 'getstatistics', [
      'rule_group_id' => $this->_ruleGroupId,
      'group_id' => $this->_groupId,
    ]);
    // retrieve pairs from prev next cache table
    $pnDupePairs = CRM_Core_BAO_PrevNextCache::retrieve($cacheKeyString, NULL, NULL, 0, 0, $select);
    $this->assertEquals(count($pnDupePairs), 0, 'Check number of remaining dupe pairs in prev next cache.');
  }

}
