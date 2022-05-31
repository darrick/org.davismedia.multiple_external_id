<?php

/**
 * Class CRM_Dedupe_DedupeMergerTest
 *
 * @group headless
 */
class CRM_MultipleExternalId_DedupeFinderTest extends CRM_MultipleExternalId_AbstractTest {

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

}
