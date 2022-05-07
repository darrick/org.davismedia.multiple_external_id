<?php
use CRM_MultipleExternalId_ExtensionUtil as E;

class CRM_MultipleExternalId_BAO_ExternalId extends CRM_MultipleExternalId_DAO_ExternalId {

  /**
   * Create a new ExternalId based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_MultipleExternalId_DAO_ExternalId|NULL
   */
  public static function create($params) {
    return self::writeRecord($params);
  }

   /**
   * Create ExternalId.
   *
   * If called in a legacy manner this, temporarily, fails back to calling the legacy function.
   *
   * @param array $params
   *
   * @return CRM_MultipleExternalId_DAO_ExternalId
   * @throws \CRM_Core_Exception
   * @deprecated
   */
  public static function add($params) {
    CRM_Core_Error::deprecatedFunctionWarning('use apiv4');
    return self::create($params);
  }

  /**
   * Process ExternalId.
   *
   * @param array $params
   * @param int $contactID
   *   Contact id.
   *
   * @param bool $skipDelete
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  public static function process($params, $contactID, $skipDelete) {
    if (empty($params)) {
      return FALSE;
    }

    $ids = self::allExternalIds($contactID);

    $existing = [];
    foreach ($ids as $key => $value) {
      $existing[$key] = $value['external_id'];
    }

    foreach ($params as $key => $values) {
      if (!empty($values['external_id']) and !array_search($values['external_id'], $existing)) {
        $values['contact_id'] = $contactID;
        $values['external_id_type'] = 1;
        self::create($values);
      }
    }
  }

  /**
   * Delete ExternalId.
   *
   * @param int $id
   *
   * @return bool
   */
  public static function del($id) {
    $obj = new self();
    $obj->id = $id;
    $obj->find();
    if ($obj->fetch()) {
      $params = [];
      CRM_Utils_Hook::pre('delete', 'ExternalId', $id, $params);
      $obj->delete();
    }
    else {
      return FALSE;
    }
    CRM_Utils_Hook::post('delete', 'ExternalId', $id, $obj);
    return TRUE;
  }

  /**
   * Given the list of params in the params array, fetch the object
   * and store the values in the values array
   *
   * @param array $params
   * @param $values
   *
   * @return array
   */
  public static function &getValues($params = [], &$values = []) {
    $external_ids = [];
    $external_id = new CRM_MultipleExternalId_DAO_ExternalId();
    $external_id->contact_id = $params['contact_id'];
    $external_id->find();

    $count = 1;
    while ($external_id->fetch()) {
      $values['external_id'][$count] = [];
      CRM_Core_DAO::storeValues($external_id, $values['external_id'][$count]);

      $external_ids[$count] = $values['external_id'][$count];
      $count++;
    }

    return $external_ids;
  }

  /**
   * Get all the external_ids for a specified contact_id.
   *
   * @param int $id
   *   The contact id.
   *
   * @param bool $updateBlankLocInfo
   *
   * @return array
   *   the array of external_id details
   */
  public static function allExternalIds($id, $updateBlankLocInfo = FALSE) {
    if (!$id) {
      return NULL;
    }

    $query = '
SELECT  id, external_id_type_id, external_id
  FROM  civicrm_external_id
 WHERE  civicrm_external_id.contact_id = %1';
    $params = [1 => [$id, 'Integer']];

    $external_ids = $values = [];
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $count = 1;
    while ($dao->fetch()) {
      $values = [
        'id' => $dao->id,
        'external_id_type_id' => $dao->external_id_type_id,
        'external_id' => $dao->external_id,
      ];

      if ($updateBlankLocInfo) {
        $external_ids[$count++] = $values;
      }
      else {
        $external_ids[$dao->id] = $values;
      }
    }
    return $external_ids;
  }


}
