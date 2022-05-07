<?php

require_once 'multiple_external_id.civix.php';
// phpcs:disable
use CRM_MultipleExternalId_ExtensionUtil as E;
// phpcs:enable

function multiple_external_id_civicrm_dupeQuery( $rgBaoObject, $op, &$objectData ) {

  switch ($op) {
    case 'dedupeIndexes':
      break;
    case 'supportedFields':
      foreach ($objectData as $contact_type => $fields) {
        $objectData[$contact_type]['civicrm_external_id'] = [
          'external_id' => 'Multiple External Id',
        ];
      }
      break;
    case 'table':
      // Find all rules contained by this script sorted by weight so that
      // their execution can be short circuited on RuleGroup::fillTable()
      $bao = new \CRM_Dedupe_BAO_DedupeRule();
      $bao->dedupe_rule_group_id = $rgBaoObject->id;
      $bao->orderBy('rule_weight DESC');
      $bao->find();

      // Generate a SQL query for each rule in the rule group that is
      // tailored to respect the param and contactId options provided.
      while ($bao->fetch()) {
        $bao->contactIds = $rgBaoObject->contactIds;
        $bao->params = $rgBaoObject->params;

        if ($bao->rule_table == 'civicrm_external_id') {
          $on = ["SUBSTR(t1.{$bao->rule_field}, 1, {$bao->rule_length}) = SUBSTR(t2.{$bao->rule_field}, 1, {$bao->rule_length})"];
          $id = 'contact_id';
          // build SELECT based on the field names containing contact ids
          // if there are params provided, id1 should be 0
          if ($bao->params) {
            $select = "t1.$id id1, {$bao->rule_weight} weight";
            $subSelect = 'id1, weight';
          }
          else {
            $select = "t1.$id id1, t2.$id id2, {$bao->rule_weight} weight";
            $subSelect = 'id1, id2, weight';
          }
          // build FROM (and WHERE, if it's a parametrised search)
          // based on whether the rule is about substrings or not
          if ($bao->params) {
            $from = "{$bao->rule_table} t1";
            $str = 'NULL';
            if (isset($bao->params['civicrm_contact']['external_identifier'])) {
              $str = trim(CRM_Utils_Type::escape($bao->params['civicrm_contact']['external_identifier'], 'String'));
            }
            if ($bao->rule_length) {
              $where[] = "SUBSTR(t1.{$bao->rule_field}, 1, {$bao->rule_length}) = SUBSTR('$str', 1, {$bao->rule_length})";
              $where[] = "t1.{$bao->rule_field} IS NOT NULL";
            }
            else {
              $where[] = "t1.{$bao->rule_field} = '$str'";
            }
          }
          else {
            if ($bao->rule_length) {
              $from = "{$bao->rule_table} t1 JOIN {$bao->rule_table} t2 ON (" . implode(' AND ', $on) . ")";
            }
            else {
              $from = "{$bao->rule_table} t1 INNER JOIN {$bao->rule_table} t2 ON (" . implode(' AND ', $innerJoinClauses) . ")";
            }
          }

          // finish building WHERE, also limit the results if requested
          if (!$bao->params) {
            $where[] = "t1.$id < t2.$id";
          }
          $query = "SELECT $select FROM $from WHERE " . implode(' AND ', $where);
          if ($bao->contactIds) {
            $cids = [];
            foreach ($bao->contactIds as $cid) {
              $cids[] = CRM_Utils_Type::escape($cid, 'Integer');
            }
            if (count($cids) == 1) {
              $query .= " AND (t1.$id = {$cids[0]}) UNION $query AND t2.$id = {$cids[0]}";
            }
            else {
              $query .= " AND t1.$id IN (" . implode(',', $cids) . ")
                UNION $query AND  t2.$id IN (" . implode(',', $cids) . ")";
            }
            // The `weight` is ambiguous in the context of the union; put the whole
            // thing in a subquery.
            $query = "SELECT $subSelect FROM ($query) subunion";
          }

          $objectData["{$bao->rule_table}.{$bao->rule_field}.{$bao->rule_weight}"] = $query;
        }

      }
      break;
    case 'threshold':
      break;
  }
}

function dma_civicrm_post($op, $objectName, $id, &$params) {
  if (array_search($objectName, ['Organization', 'Household', 'Individual'])
    and array_search($op, ['create', 'edit'])
    and !empty($params->external_identifier)) {
    $external_params = [
      [
      'external_id' => $params->external_identifier,
      ]
    ];
    CRM_MultipleExternalId_BAO_ExternalId::process($external_params, $id, false);
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
        'tables' => array($db_default .'civicrm_external_id'),
        // URL to view this data for this contact,
        // in this case using CiviCRM's native URL utility
        //'url'    => CRM_Utils_System::url('civicrm/civitest/foo', 'action=browse&cid=$cid'),
        // NOTE: '$cid' will be replaced with correct CiviCRM contact ID.
      );
      break;

    case 'cidRefs':
      // Use entityTypes hook instead as cidRefs is deprecated in this hook.
      $data[$db_default . 'civicrm_external_id'] = ['contact_id'];
      break;

    case 'eidRefs':
      // Add references to civitest_bar table, which is keyed to
      // civicrm_contact.id using `bar_entity_id` column, when the value
      // in its `entity_table` column is equal to 'civicrm_contact'. By
      // adding this to $data, records in this table will be automatically
      // included in the merge.
      break;

    case 'sqls':
      // Note that this hook can be called twice with $type = 'sqls': once with $tables
      // and once without. In our case, SQL statements related to table `civitest_foo`
      // will be listed in $data when $tables is set; SQL statements related to table
      // `civitest_bar` will be listed in $data when $tables is NOT set.  The deciding
      // factor here is that `civitest_foo` was referenced above as part of the 'relTables'
      // data, whereas `civitest_bar` was not.
      if ($tables) {
        // Nothing to do in our case. In some cases, you might want to find and
        // modify existing SQL statements in $data.
      }
      else {
        // Nothing to do in our case. In some cases, you might want to find and
        // modify existing SQL statements in $data.
      }
      break;

  }
}

/**
 * Implementation of hook_civicrm_alterUFFIelds
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
