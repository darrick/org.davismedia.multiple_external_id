<?php


/**
 * Class CRM_Dedupe_DedupeMergerTest
 *
 * @group headless
 */
class CRM_MultipleExternalId_MergerTest extends CRM_MultipleExternalId_AbstractTest {

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
