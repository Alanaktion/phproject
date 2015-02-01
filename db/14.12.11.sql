# This database update occured after commit f7c42f23b8

# Add Version checking to the database
CREATE TABLE IF NOT EXISTS `config` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `attribute` varchar(255)  NULL,
  `value` varchar(255)  NULL,
  UNIQUE KEY `attribute` (`attribute`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `config` (`attribute`, `value`) VALUES ('version', '0.0.0');

# Add start_date to issues and issue_detail view
ALTER TABLE `issue`  ADD `start_date` date NULL AFTER `deleted_date`;

CREATE OR REPLACE VIEW `issue_detail` AS
select `issue`.`id` AS `id`,`issue`.`status` AS `status`,`issue`.`type_id` AS `type_id`,`issue`.`name` AS `name`,`issue`.`description` AS `description`,`issue`.`parent_id` AS `parent_id`,`issue`.`author_id` AS `author_id`,`issue`.`owner_id` AS `owner_id`,`issue`.`priority` AS `priority`,`issue`.`hours_total` AS `hours_total`,`issue`.`hours_remaining` AS `hours_remaining`,`issue`.`hours_spent` AS `hours_spent`,`issue`.`created_date` AS `created_date`,`issue`.`closed_date` AS `closed_date`,`issue`.`deleted_date` AS `deleted_date`,`issue`.`start_date` AS `start_date`,`issue`.`due_date` AS `due_date`,isnull(`issue`.`due_date`) AS `has_due_date`,`issue`.`repeat_cycle` AS `repeat_cycle`,`issue`.`sprint_id` AS `sprint_id`,`sprint`.`name` AS `sprint_name`,`sprint`.`start_date` AS `sprint_start_date`,`sprint`.`end_date` AS `sprint_end_date`,`type`.`name` AS `type_name`,`status`.`name` AS `status_name`,`status`.`closed` AS `status_closed`,`priority`.`id` AS `priority_id`,`priority`.`name` AS `priority_name`,`author`.`username` AS `author_username`,`author`.`name` AS `author_name`,`author`.`email` AS `author_email`,`author`.`task_color` AS `author_task_color`,`owner`.`username` AS `owner_username`,`owner`.`name` AS `owner_name`,`owner`.`email` AS `owner_email`,`owner`.`task_color` AS `owner_task_color` from ((((((`issue` left join `user` `author` on((`issue`.`author_id` = `author`.`id`))) left join `user` `owner` on((`issue`.`owner_id` = `owner`.`id`))) left join `issue_status` `status` on((`issue`.`status` = `status`.`id`))) left join `issue_priority` `priority` on((`issue`.`priority` = `priority`.`value`))) left join `issue_type` `type` on((`issue`.`type_id` = `type`.`id`))) left join `sprint` on((`issue`.`sprint_id` = `sprint`.`id`)));

# Update Version
UPDATE `config` SET `value` = '14.12.11' WHERE `attribute` = 'version';
