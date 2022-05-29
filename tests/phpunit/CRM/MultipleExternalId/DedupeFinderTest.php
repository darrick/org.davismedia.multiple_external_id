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
   * @throws \CRM_Core_Exception
   */
  public function testDupesByParams() {

    $this->createDupeContacts();
    $this->createRuleGroup();

    $fields = [
      'first_name' => 'bob',
      'last_name' => 'dobbs',
      'email' => 'dobbs@bob.com',
      'external_identifier' => 1,
    ];
    $ids = CRM_Contact_BAO_Contact::getDuplicateContacts($fields, 'Individual', 'General', [], TRUE, NULL, ['event_id' => 1]);

    // Check with default Individual-General rule
    $this->assertEquals(count($ids), 2, 'Check Individual-General rule for dupesByParams().');

  }

}
