INSERT INTO
   `civicrm_option_group` (`name`, `title`, `data_type`, `is_reserved`, `is_active`, `is_locked`)
VALUES
   ('external_id_type'                  , '{ts escape="sql"}External ID Type{/ts}'                       , NULL, 1, 1, 0);


SELECT @option_group_id_external_id        := max(id) from civicrm_option_group where name = 'external_id_type';

INSERT INTO
   `civicrm_option_value` (`option_group_id`, `label`, `value`, `name`, `grouping`, `filter`, `is_default`, `weight`, `description`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `visibility_id`, `icon`)
VALUES
   (@option_group_id_external_id, 'Main',     1, 'Main',     NULL, 0, 1, 1, NULL, 0, 0, 1, NULL, NULL, NULL);

