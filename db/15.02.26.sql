# Update issue_detail view
ALTER VIEW `issue_detail` AS
SELECT
    `issue`.`id`              AS `id`,
    `issue`.`status`          AS `status`,
    `issue`.`type_id`         AS `type_id`,
    `issue`.`name`            AS `name`,
    `issue`.`description`     AS `description`,
    `issue`.`parent_id`       AS `parent_id`,
    `issue`.`author_id`       AS `author_id`,
    `issue`.`owner_id`        AS `owner_id`,
    `issue`.`priority`        AS `priority`,
    `issue`.`hours_total`     AS `hours_total`,
    `issue`.`hours_remaining` AS `hours_remaining`,
    `issue`.`hours_spent`     AS `hours_spent`,
    `issue`.`created_date`    AS `created_date`,
    `issue`.`closed_date`     AS `closed_date`,
    `issue`.`deleted_date`    AS `deleted_date`,
    `issue`.`start_date`      AS `start_date`,
    `issue`.`due_date`        AS `due_date`,
    ISNULL(`issue`.`due_date`) AS `has_due_date`,
    `issue`.`repeat_cycle`    AS `repeat_cycle`,
    `issue`.`sprint_id`       AS `sprint_id`,
    `sprint`.`name`           AS `sprint_name`,
    `sprint`.`start_date`     AS `sprint_start_date`,
    `sprint`.`end_date`       AS `sprint_end_date`,
    `type`.`name`             AS `type_name`,
    `status`.`name`           AS `status_name`,
    `status`.`closed`         AS `status_closed`,
    `priority`.`id`           AS `priority_id`,
    `priority`.`name`         AS `priority_name`,
    `author`.`username`       AS `author_username`,
    `author`.`name`           AS `author_name`,
    `author`.`email`          AS `author_email`,
    `author`.`task_color`     AS `author_task_color`,
    `owner`.`username`        AS `owner_username`,
    `owner`.`name`            AS `owner_name`,
    `owner`.`email`           AS `owner_email`,
    `owner`.`task_color`      AS `owner_task_color`,
    `parent`.`name`           AS `parent_name`
FROM `issue`
LEFT JOIN `user` `author` ON `issue`.`author_id` = `author`.`id`
LEFT JOIN `user` `owner` ON `issue`.`owner_id` = `owner`.`id`
LEFT JOIN `issue_status` `status` ON `issue`.`status` = `status`.`id`
LEFT JOIN `issue_priority` `priority` ON `issue`.`priority` = `priority`.`value`
LEFT JOIN `issue_type` `type` ON `issue`.`type_id` = `type`.`id`
LEFT JOIN `sprint` ON `issue`.`sprint_id` = `sprint`.`id`
LEFT JOIN `issue` `parent` ON `issue`.`parent_id` = `parent`.`id`;

# Update Version
UPDATE `config` SET `value` = '15.02.26' WHERE `attribute` = 'version';
