<?php

require_once 'multiple_external_id.civix.php';
// phpcs:disable
use CRM_MultipleExternalId_ExtensionUtil as E;
// phpcs:enable

function multiple_external_id_civicrm_dupeQuery($baoObject, $op, &$objectData) {

  switch ($op) {
    case 'dedupeIndexes':
      break;

    case 'table':
      //\Drupal::logger('my_module')->notice(print_r($baoObject, TRUE));
      //\Drupal::logger('my_module')->notice(print_r($objectData, TRUE));

      break;

    case 'supportedFields':
      foreach ($objectData as $contact_type => $fields) {
        $objectData[$contact_type]['civicrm_external_id'] = [
          'external_id' => 'Multiple External Id',
        ];
      }
      break;
  }
}

function multiple_external_id_civicrm_findDuplicates($dedupeParams, &$dedupeResults, $contextParams) {
  //\Drupal::logger('my_module')->notice(print_r($dedupeResults, TRUE));
  //\Drupal::logger('my_module')->notice(print_r($dedupeParams, TRUE));
  $params = array_intersect_key($dedupeParams, ['civicrm_contact' => 1, 'contact_type' => 1]);

  if (count($params) and array_key_exists('external_identifier', $params['civicrm_contact'])) {

    try {
      $result = \Civi\Api4\ExternalId::get()
        ->addSelect('contact_id')
        ->addWhere('external_id', '=', $params['civicrm_contact']['external_identifier'])
        ->execute();
      if ($result->count()) {
        foreach ($result as $value) {
          $dedupeResults['ids'][] = $value['contact_id'];
        }
        $dedupeResults['handled'] = TRUE;
      }
    }
    catch (CiviCRM_API3_Exception $e) {
    }
  }
  //\Drupal::logger('my_module')->notice(print_r($dedupeResults, TRUE));
}

function multiple_external_id_civicrm_import($object, $usage, &$objectRef, &$params) {
  if ($object == 'Contact') {
    //try {
    $contacts = \Civi\Api4\Contact::get()
      ->addSelect('external_identifier')
      ->addWhere('id', '=', $params['contactID'])
      ->execute();

    if (!empty($contacts) and !empty($contacts[0]['external_identifier'])) {
      $external_params = [
          [
            'external_id' => $contacts[0]['external_identifier'],
          ],
      ];

      CRM_MultipleExternalId_BAO_ExternalId::process($external_params, $params['contactID'], FALSE);
      $results = \Civi\Api4\Contact::update()
        ->addValue('external_identifier', '')
        ->addWhere('id', '=', $params['contactID'])
        ->execute();
    }
    //}
    //catch (CiviCRM_API3_Exception $e) {
    //}
  }
}

function multiple_external_id_civicrm_civicrm_post($op, $objectName, $id, &$params) {
  return;
  if (array_search($objectName, ['Organization', 'Household', 'Individual'])
    and array_search($op, ['create', 'edit'])
    and !empty($params->external_identifier)) {
    $external_params = [
      [
        'external_id' => $params->external_identifier,
      ],
    ];
    CRM_MultipleExternalId_BAO_ExternalId::process($external_params, $id, FALSE);
  }
}

function multiple_external_id_civicrm_merge($type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL) {

  // If you are using Drupal and you use separate DBs for Drupal and
  // CiviCRM, use the following to prefix your tables with the name of the
  // Drupal database.
  global $db_url;
  if (!empty($db_url)) {
    $db_default = is_array($db_url) ? $db_url['default'] : $db_url;
    $db_default = ltrim(parse_url($db_default, PHP_URL_PATH), '/');
  }
  else {
    $db_default = '';
  }

  switch ($type) {
    case 'relTables':
      // Allow user to decide whether or not to merge records in `civitest_foo` table
      $data['rel_table_external_id'] = array(
        // Title as shown to user for this type of data
        'title'  => ts('Multiple External ID'),
        // Name of database table holding these records
        'tables' => array($db_default . 'civicrm_external_id'),
        // URL to view this data for this contact,
        // in this case using CiviCRM's native URL utility
        'url'    => CRM_Utils_System::url('civicrm/external_id/contacttab', 'action=browse&cid=$cid'),
        // NOTE: '$cid' will be replaced with correct CiviCRM contact ID.
      );
      break;

    case 'sqls':
      $data[] = "UPDATE IGNORE civicrm_external_id SET contact_id = $mainId WHERE contact_id = $otherId";
      $data[] = "DELETE FROM civicrm_external_id WHERE contact_id = $otherId";
      break;

    case 'batch':
      unset($data['migration_info']['move_rel_table_external_id']);
      break;

  }
}

/**
 * Implements hook_civicrm_alterUFFIelds().
 */
function multiple_external_id_civicrm_alterUFFields(&$fields) {
  // Include grant fields in the permissible array
  //dpm($fields);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function multiple_external_id_civicrm_config(&$config) {
  _multiple_external_id_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function multiple_external_id_civicrm_xmlMenu(&$files) {
  _multiple_external_id_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function multiple_external_id_civicrm_install() {
  _multiple_external_id_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function multiple_external_id_civicrm_postInstall() {
  _multiple_external_id_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function multiple_external_id_civicrm_uninstall() {
  _multiple_external_id_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function multiple_external_id_civicrm_enable() {
  _multiple_external_id_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function multiple_external_id_civicrm_disable() {
  _multiple_external_id_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function multiple_external_id_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _multiple_external_id_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function multiple_external_id_civicrm_managed(&$entities) {
  _multiple_external_id_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Add CiviCase types provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function multiple_external_id_civicrm_caseTypes(&$caseTypes) {
  _multiple_external_id_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Add Angular modules provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function multiple_external_id_civicrm_angularModules(&$angularModules) {
  // Auto-add module files from ./ang/*.ang.php
  _multiple_external_id_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function multiple_external_id_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _multiple_external_id_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function multiple_external_id_civicrm_entityTypes(&$entityTypes) {
  _multiple_external_id_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function multiple_external_id_civicrm_themes(&$themes) {
  _multiple_external_id_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function multiple_external_id_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function multiple_external_id_civicrm_navigationMenu(&$menu) {
//  _multiple_external_id_civix_insert_navigation_menu($menu, 'Mailings', [
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ]);
//  _multiple_external_id_civix_navigationMenu($menu);
//}
