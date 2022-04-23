<?php
use CRM_MultipleExternalId_ExtensionUtil as E;

class CRM_MultipleExternalId_BAO_ExternalId extends CRM_MultipleExternalId_DAO_ExternalId {

  /**
   * Create a new ExternalId based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_MultipleExternalId_DAO_ExternalId|NULL
   *
  public static function create($params) {
    $className = 'CRM_MultipleExternalId_DAO_ExternalId';
    $entityName = 'ExternalId';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
