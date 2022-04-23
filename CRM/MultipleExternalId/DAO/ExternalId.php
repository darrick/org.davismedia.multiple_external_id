<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.davismedia.multiple_external_id/xml/schema/CRM/MultipleExternalId/ExternalId.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:248117d7e33bf0aaca368a837be3df41)
 */
use CRM_MultipleExternalId_ExtensionUtil as E;

/**
 * Database access object for the ExternalId entity.
 */
class CRM_MultipleExternalId_DAO_ExternalId extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_external_id';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique ExternalId ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * FK to Contact
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $contact_id;

  /**
   * Unique trusted external ID (generally from a legacy app/datasource). Particularly useful for deduping operations.
   *
   * @var string|null
   *   (SQL type: varchar(64))
   *   Note that values will be retrieved from the database as a string.
   */
  public $external_id;

  /**
   * Which External ID type does this External ID belong to.
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $external_id_type_id;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_external_id';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('External Ids') : E::ts('External Id');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contact_id', 'civicrm_contact', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('Unique ExternalId ID'),
          'required' => TRUE,
          'where' => 'civicrm_external_id.id',
          'table_name' => 'civicrm_external_id',
          'entity' => 'ExternalId',
          'bao' => 'CRM_MultipleExternalId_BAO_ExternalId',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'contact_id' => [
          'name' => 'contact_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to Contact'),
          'where' => 'civicrm_external_id.contact_id',
          'table_name' => 'civicrm_external_id',
          'entity' => 'ExternalId',
          'bao' => 'CRM_MultipleExternalId_BAO_ExternalId',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'add' => NULL,
        ],
        'external_id' => [
          'name' => 'external_id',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('External ID'),
          'description' => E::ts('Unique trusted external ID (generally from a legacy app/datasource). Particularly useful for deduping operations.'),
          'maxlength' => 64,
          'size' => 8,
          'import' => TRUE,
          'where' => 'civicrm_external_id.external_id',
          'headerPattern' => '/external\s?id/i',
          'dataPattern' => '/^\d{11,}$/',
          'export' => TRUE,
          'table_name' => 'civicrm_external_id',
          'entity' => 'ExternalId',
          'bao' => 'CRM_MultipleExternalId_BAO_ExternalId',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
            'label' => E::ts("External ID"),
          ],
          'add' => '1.1',
        ],
        'external_id_type_id' => [
          'name' => 'external_id_type_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('External ID Type'),
          'description' => E::ts('Which External ID type does this External ID belong to.'),
          'where' => 'civicrm_external_id.external_id_type_id',
          'table_name' => 'civicrm_external_id',
          'entity' => 'ExternalId',
          'bao' => 'CRM_MultipleExternalId_BAO_ExternalId',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
          ],
          'pseudoconstant' => [
            'optionGroupName' => 'external_id_type',
            'optionEditPath' => 'civicrm/admin/options/external_id_type',
          ],
          'add' => '3.2',
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'external_id', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'external_id', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'UI_external_id_type_id' => [
        'name' => 'UI_external_id_type_id',
        'field' => [
          0 => 'external_id_type_id',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_external_id::0::external_id_type_id',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
