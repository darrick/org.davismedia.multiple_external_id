SELECT @option_group_id_external_id        := max(id) from civicrm_option_group where name = 'external_id_type';

DELETE FROM `civicrm_option_value` WHERE `option_group_id` = @option_group_id_external_id;

DELETE FROM `civicrm_option_group` WHERE `id` = @option_group_id_external_id;

