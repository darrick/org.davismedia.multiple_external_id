<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This code is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test\CiviEnvBuilder;

/**
 * @file
 * File for the CRM_Contact_Imports_Parser_ContactTest class.
 */

use Civi\Api4\UserJob;

/**
 *  Test contact import parser.
 *
 * @package CiviCRM
 * @group headless
 */
class CRM_ExtendedId_ImportTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3DocTrait;
  use \Civi\Test\GenericAssertionsTrait;
  use \Civi\Test\DbTestTrait;
  use \Civi\Test\ContactTestTrait;


  /**
   * Main entity for the class.
   *
   * @var string
   */
  protected $entity = 'Contact';

  protected $_ruleGroupId;

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
    CRM_Core_DAO::executeQuery("DELETE r FROM civicrm_dedupe_rule_group rg INNER JOIN civicrm_dedupe_rule r ON rg.id = r.dedupe_rule_group_id WHERE rg.is_reserved = 0 AND used = 'General'");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_dedupe_rule_group WHERE is_reserved = 0 AND used = 'General'");
    parent::tearDown();
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

    foreach (['organization_name', 'external_id'] as $field) {
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
   * Set up the underlying contact.
   *
   * @param array $params
   *   Optional extra parameters to set.
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function setUpBaseContact($params = []) {
    $originalValues = array_merge([
      'organization_name' => 'Common Frequency',
      'email' => 'info@cf.org',
    ], $params);
    $this->runImport($originalValues, CRM_Import_Parser::DUPLICATE_UPDATE, CRM_Import_Parser::VALID);
    $result = $this->callAPISuccessGetSingle('Contact', $originalValues);
    return [$originalValues, $result];
  }

  /**
   * Test import parser will update based on a rule match.
   *
   * In this case the contact has no external identifier.
   *
   * @throws \CRM_Core_Exception
   */
  public function testImportParserWithUpdateWithoutExternalIdentifier(): void {
    $this->createRuleGroup();
    [$originalValues, $result] = $this->setUpBaseContact();
    $originalValues['email'] = 'info2@cf.org';
    $this->runImport($originalValues, CRM_Import_Parser::DUPLICATE_UPDATE, CRM_Import_Parser::VALID, [], NULL, $this->_ruleGroupId);
    $originalValues['id'] = $result['id'];
    $this->assertEquals('info2@cf.org', $this->callAPISuccessGetValue('Contact', ['id' => $result['id'], 'return' => 'email']));
    $this->callAPISuccessGetSingle('Contact', $originalValues);
  }

  /**
   * Test import parser will update based on a rule match.
   *
   * In this case the contact has no external identifier.
   *
   * @throws \CRM_Core_Exception
   */
  public function testImportParserWithUpdateWithExternalIdentifier(): void {
    $this->createRuleGroup();
    [$originalValues, $result] = $this->setUpBaseContact([
      'api.ExternalId.create' => [
        'external_id' => '1',
      ],
    ]);
    unset($originalValues['api.ExternalId.create']);
    $originalValues['organization_name'] = "Davis Media Access";
    $originalValues['email'] = 'info2@cf.org';
    $originalValues['external_identifier'] = '1';
    $this->runImport($originalValues, CRM_Import_Parser::DUPLICATE_UPDATE, CRM_Import_Parser::VALID, [], NULL, $this->_ruleGroupId);
    $originalValues['id'] = $result['id'];
    $this->assertEquals('info2@cf.org', $this->callAPISuccessGetValue('Contact', ['id' => $result['id'], 'return' => 'email']));
    unset($originalValues['external_identifier']);
    $this->callAPISuccessGetSingle('Contact', $originalValues);
  }

  /**
   * Test import parser will update based on a rule match.
   *
   * In this case the contact has no external identifier.
   *
   * @throws \CRM_Core_Exception
   */
  public function testImportParserWithUpdateWithDifferentExternalIdentifier(): void {
    $this->createRuleGroup();
    [$originalValues, $result] = $this->setUpBaseContact([
      'api.ExternalId.create' => [
        'external_id' => '1',
      ],
    ]);
    unset($originalValues['api.ExternalId.create']);
    $originalValues['email'] = 'info2@cf.org';
    $originalValues['external_identifier'] = '5';
    $this->runImport($originalValues, CRM_Import_Parser::DUPLICATE_UPDATE, CRM_Import_Parser::VALID, [], NULL, $this->_ruleGroupId);
    $originalValues['id'] = $result['id'];
    $this->assertEquals('info2@cf.org', $this->callAPISuccessGetValue('Contact', ['id' => $result['id'], 'return' => 'email']));
    unset($originalValues['external_identifier']);
    $this->callAPISuccessGetSingle('Contact', $originalValues);
  }

  /**
   * Run the import parser.
   *
   * @param array $originalValues
   *
   * @param int $onDuplicateAction
   * @param int $expectedResult
   * @param array|null $mapperLocType
   *   Array of location types that map to the input arrays.
   * @param array|null $fields
   *   Array of field names. Will be calculated from $originalValues if not passed in, but
   *   that method does not cope with duplicates.
   * @param int|null $ruleGroupId
   *   To test against a specific dedupe rule group, pass its ID as this argument.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  protected function runImport(array $originalValues, $onDuplicateAction, $expectedResult, $mapperLocType = [], $fields = NULL, int $ruleGroupId = NULL): void {
    if (!$fields) {
      $fields = array_keys($originalValues);
    }
    $values = array_values($originalValues);
    $mapper = [];
    foreach ($fields as $index => $field) {
      $mapper[] = [$field, $mapperLocType[$index] ?? NULL, $field === 'phone' ? 1 : NULL];
    }
    $userJobID = $this->getUserJobID(['mapper' => $mapper, 'onDuplicate' => $onDuplicateAction, 'dedupe_rule_id' => $ruleGroupId]);
    $parser = new CRM_Contact_Import_Parser_Contact($fields);
    $parser->setUserJobID($userJobID);
    $parser->_dedupeRuleGroupID = $ruleGroupId;
    $parser->init();
    $this->assertEquals($expectedResult, $parser->import($onDuplicateAction, $values), 'Return code from parser import was not as expected');
  }

  /**
   * @return mixed
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getUserJobID($submittedValues = []) {
    $userJobID = UserJob::create()->setValues([
      'metadata' => [
        'submitted_values' => array_merge([
          'contactType' => CRM_Import_Parser::CONTACT_INDIVIDUAL,
          'contactSubType' => '',
          'doGeocodeAddress' => 0,
          'dataSource' => 'CRM_Import_DataSource_SQL',
          'sqlQuery' => 'SELECT first_name FROM civicrm_contact',
          'onDuplicate' => CRM_Import_Parser::DUPLICATE_SKIP,
          'dedupe_rule_id' => NULL,
          'dateFormats' => CRM_Core_Form_Date::DATE_yyyy_mm_dd,
        ], $submittedValues),
      ],
      'status_id:name' => 'draft',
      'type_id:name' => 'contact_import',
    ])->execute()->first()['id'];
    if ($submittedValues['dataSource'] ?? NULL === 'CRM_Import_DataSource') {
      $dataSource = new CRM_Import_DataSource_CSV($userJobID);
    }
    else {
      $dataSource = new CRM_Import_DataSource_SQL($userJobID);
    }
    $dataSource->initialize();
    return $userJobID;
  }

}
