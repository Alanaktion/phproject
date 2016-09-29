CREATE TABLE `attribute` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(64) NOT NULL
,  `type` text  NOT NULL DEFAULT 'text'
,  `default` text
);
CREATE TABLE `attribute_issue_type` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `attribute_id` integer  NOT NULL
,  `issue_type_id` integer  NOT NULL
,  CONSTRAINT `attribute_issue_type_attribute_id` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `attribute_issue_type_issue_type_id` FOREIGN KEY (`issue_type_id`) REFERENCES `issue_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `attribute_value` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `attribute_id` integer  NOT NULL
,  `issue_id` integer  NOT NULL
,  `value` text NOT NULL
,  CONSTRAINT `attribute_value_attribute_id` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `config` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT
,  `attribute` varchar(255) DEFAULT NULL
,  `value` varchar(255) DEFAULT NULL
,  UNIQUE (`attribute`)
);
CREATE TABLE `issue` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `status` integer  NOT NULL DEFAULT '1'
,  `type_id` integer  NOT NULL DEFAULT '1'
,  `name` varchar(255) NOT NULL
,  `description` mediumtext NOT NULL
,  `parent_id` integer  DEFAULT NULL
,  `author_id` integer  NOT NULL
,  `owner_id` integer  DEFAULT NULL
,  `priority` integer NOT NULL DEFAULT '-9'
,  `hours_total` real  DEFAULT NULL
,  `hours_remaining` real  DEFAULT NULL
,  `hours_spent` real  DEFAULT NULL
,  `created_date` datetime NOT NULL
,  `closed_date` datetime DEFAULT NULL
,  `deleted_date` datetime DEFAULT NULL
,  `start_date` date DEFAULT NULL
,  `due_date` date DEFAULT NULL
,  `repeat_cycle` varchar(10) DEFAULT NULL
,  `sprint_id` integer  DEFAULT NULL
,  CONSTRAINT `issue_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
,  CONSTRAINT `issue_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
,  CONSTRAINT `issue_status` FOREIGN KEY (`status`) REFERENCES `issue_status` (`id`) ON UPDATE CASCADE
,  CONSTRAINT `issue_type_id` FOREIGN KEY (`type_id`) REFERENCES `issue_type` (`id`) ON UPDATE CASCADE
);
CREATE TABLE `issue_TEST` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `status` integer  NOT NULL DEFAULT '1'
,  `type_id` integer  NOT NULL DEFAULT '1'
,  `name` varchar(255) NOT NULL
,  `description` mediumtext NOT NULL
,  `parent_id` integer  DEFAULT NULL
,  `author_id` integer  NOT NULL
,  `owner_id` integer  DEFAULT NULL
,  `priority` integer NOT NULL DEFAULT '-9'
,  `hours_total` real  DEFAULT NULL
,  `hours_remaining` real  DEFAULT NULL
,  `hours_spent` real  DEFAULT NULL
,  `created_date` datetime NOT NULL
,  `closed_date` datetime DEFAULT NULL
,  `deleted_date` datetime DEFAULT NULL
,  `start_date` date DEFAULT NULL
,  `due_date` date DEFAULT NULL
,  `repeat_cycle` varchar(10) DEFAULT NULL
,  `sprint_id` integer  DEFAULT NULL
,  CONSTRAINT `issue_TEST_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
,  CONSTRAINT `issue_TEST_ibfk_2` FOREIGN KEY (`sprint_id`) REFERENCES `sprint` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
,  CONSTRAINT `issue_TEST_ibfk_3` FOREIGN KEY (`status`) REFERENCES `issue_status` (`id`) ON UPDATE CASCADE
,  CONSTRAINT `issue_TEST_ibfk_4` FOREIGN KEY (`type_id`) REFERENCES `issue_type` (`id`) ON UPDATE CASCADE
);
CREATE TABLE `issue_backlog` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `type_id` integer  NOT NULL
,  `sprint_id` integer  DEFAULT NULL
,  `issues` blob NOT NULL
,  CONSTRAINT `issue_backlog_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `issue_backlog_type_id` FOREIGN KEY (`type_id`) REFERENCES `issue_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `issue_backlog_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `issue_checklist` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `text` text NOT NULL
,  `checked` integer  NOT NULL DEFAULT '0'
,  `created_date` datetime NOT NULL
,  CONSTRAINT `issue_checklist_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE
);
CREATE TABLE `issue_comment` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `text` text NOT NULL
,  `file_id` integer  DEFAULT NULL
,  `created_date` datetime NOT NULL
,  CONSTRAINT `comment_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `comment_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
);
CREATE TABLE `issue_dependency` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `dependency_id` integer  NOT NULL
,  `dependency_type` char(2) NOT NULL
,  UNIQUE (`issue_id`,`dependency_id`)
,  CONSTRAINT `issue_dependency_ibfk_2` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `issue_dependency_ibfk_3` FOREIGN KEY (`dependency_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `issue_file` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `filename` varchar(255) NOT NULL DEFAULT ''
,  `disk_filename` varchar(255) NOT NULL DEFAULT ''
,  `disk_directory` varchar(255) DEFAULT NULL
,  `filesize` integer NOT NULL DEFAULT '0'
,  `content_type` varchar(255) DEFAULT ''
,  `digest` varchar(40) NOT NULL
,  `downloads` integer NOT NULL DEFAULT '0'
,  `user_id` integer  NOT NULL DEFAULT '0'
,  `created_date` datetime NOT NULL
,  `deleted_date` datetime DEFAULT NULL
);
CREATE TABLE `issue_file_missing` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `filename` varchar(255) NOT NULL DEFAULT ''
,  `disk_filename` varchar(255) NOT NULL DEFAULT ''
,  `disk_directory` varchar(255) DEFAULT NULL
,  `filesize` integer NOT NULL DEFAULT '0'
,  `content_type` varchar(255) DEFAULT ''
,  `digest` varchar(40) NOT NULL
,  `downloads` integer NOT NULL DEFAULT '0'
,  `user_id` integer  NOT NULL DEFAULT '0'
,  `created_date` datetime NOT NULL
,  `deleted_date` datetime DEFAULT NULL
);
CREATE TABLE `issue_priority` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `value` integer NOT NULL
,  `name` varchar(64) NOT NULL
);
CREATE TABLE `issue_status` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(32) NOT NULL
,  `closed` integer NOT NULL DEFAULT '0'
,  `taskboard` integer NOT NULL DEFAULT '0'
,  `taskboard_sort` integer  DEFAULT NULL
);
CREATE TABLE `issue_tag` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `tag` varchar(60) NOT NULL
,  `issue_id` integer  NOT NULL
,  CONSTRAINT `issue_tag_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `issue_type` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(32) NOT NULL
);
CREATE TABLE `issue_update` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `created_date` datetime NOT NULL
,  `comment_id` integer  DEFAULT NULL
,  `notify` integer  DEFAULT NULL
,  CONSTRAINT `update_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `issue_update_field` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_update_id` integer  NOT NULL
,  `field` varchar(64) NOT NULL
,  `old_value` text NOT NULL
,  `new_value` text NOT NULL
,  CONSTRAINT `issue_update_field_update` FOREIGN KEY (`issue_update_id`) REFERENCES `issue_update` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `issue_watcher` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `issue_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  UNIQUE (`issue_id`,`user_id`)
);
CREATE TABLE `poker_vote` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `project_id` integer  NOT NULL
,  `vote` integer  NOT NULL
,  UNIQUE (`user_id`,`project_id`)
,  CONSTRAINT `poker_vote_project_id` FOREIGN KEY (`project_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `poker_vote_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `pto_request` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `employee_name` varchar(120) NOT NULL
,  `created_date` datetime NOT NULL
,  `dates_requested_off` varchar(120) NOT NULL
,  `total_pto_hours` float NOT NULL
,  `request_type` varchar(20) NOT NULL
,  `person_taking_responsibilities` varchar(120) DEFAULT NULL
,  `supervisor_id` integer  NOT NULL
,  `approved_date` datetime DEFAULT NULL
,  `approved_user_id` integer  DEFAULT NULL
,  `notes` text COLLATE BINARY
,  CONSTRAINT `pto_request_approved_user_id` FOREIGN KEY (`approved_user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
,  CONSTRAINT `pto_request_supervisor_id` FOREIGN KEY (`supervisor_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
,  CONSTRAINT `pto_request_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
);
CREATE TABLE `pto_request_manager` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  CONSTRAINT `pto_request_manager_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `release` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(200) NOT NULL
,  `description` text NOT NULL
,  `created_date` datetime NOT NULL
,  `target_date` date DEFAULT NULL
,  `closed_date` datetime DEFAULT NULL
);
CREATE TABLE `release_issue` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `release_id` integer  NOT NULL
,  `issue_id` integer  NOT NULL
,  UNIQUE (`issue_id`)
,  CONSTRAINT `release_issue_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `release_issue_release` FOREIGN KEY (`release_id`) REFERENCES `release` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `session` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `token` varchar(64) NOT NULL
,  `ip` varchar(39) NOT NULL
,  `user_id` integer  NOT NULL
,  `created` datetime NOT NULL
,  UNIQUE (`token`,`ip`)
,  CONSTRAINT `session_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `sprint` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(60) NOT NULL
,  `start_date` date NOT NULL
,  `end_date` date NOT NULL
);
CREATE TABLE `todo_item` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `order` integer NOT NULL
,  `text` text NOT NULL
,  `completed` integer  NOT NULL DEFAULT '0'
,  CONSTRAINT `todo_item_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `user` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `username` varchar(32) DEFAULT NULL
,  `email` varchar(64) DEFAULT NULL
,  `name` varchar(32) NOT NULL
,  `password` char(40) DEFAULT NULL
,  `salt` char(32) DEFAULT NULL
,  `role` text  NOT NULL DEFAULT 'user'
,  `rank` integer  NOT NULL DEFAULT '0'
,  `task_color` char(6) DEFAULT NULL
,  `theme` varchar(64) DEFAULT NULL
,  `language` varchar(5) DEFAULT NULL
,  `avatar_filename` varchar(64) DEFAULT NULL
,  `api_key` varchar(40) DEFAULT NULL
,  `api_visible` integer  NOT NULL DEFAULT '0'
,  `options` blob
,  `created_date` datetime NOT NULL
,  `deleted_date` datetime DEFAULT NULL
,  UNIQUE (`username`)
,  UNIQUE (`email`)
);
CREATE TABLE `user_group` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `group_id` integer  NOT NULL
,  `manager` integer NOT NULL DEFAULT '0'
);
CREATE TABLE `user_note` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `text` text NOT NULL
,  `updated` datetime NOT NULL
,  CONSTRAINT `user_note_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `wiki_page` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(64) NOT NULL
,  `slug` varchar(64) NOT NULL
,  `content` mediumtext NOT NULL
,  `parent_id` integer  DEFAULT NULL
,  `created_date` datetime NOT NULL
,  `deleted_date` datetime DEFAULT NULL
);
CREATE TABLE `wiki_page_update` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `wiki_page_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `old_name` varchar(64) DEFAULT NULL
,  `new_name` varchar(64) NOT NULL
,  `old_content` mediumtext
,  `new_content` mediumtext NOT NULL
,  `created_date` datetime NOT NULL
);

CREATE INDEX "idx_issue_TEST_sprint_id" ON "issue_TEST" (`sprint_id`);
CREATE INDEX "idx_issue_TEST_repeat_cycle" ON "issue_TEST" (`repeat_cycle`);
CREATE INDEX "idx_issue_TEST_due_date" ON "issue_TEST" (`due_date`);
CREATE INDEX "idx_issue_TEST_type_id" ON "issue_TEST" (`type_id`);
CREATE INDEX "idx_issue_TEST_parent_id" ON "issue_TEST" (`parent_id`);
CREATE INDEX "idx_issue_TEST_status" ON "issue_TEST" (`status`);
CREATE INDEX "idx_issue_TEST_issue_owner_id" ON "issue_TEST" (`owner_id`);
CREATE INDEX "idx_todo_item_todo_item_user" ON "todo_item" (`user_id`);
CREATE INDEX "idx_issue_update_field_issue_update_field_update_id" ON "issue_update_field" (`issue_update_id`);
CREATE INDEX "idx_issue_comment_issue_id" ON "issue_comment" (`issue_id`);
CREATE INDEX "idx_issue_comment_user" ON "issue_comment" (`user_id`);
CREATE INDEX "idx_issue_tag_issue_tag_tag" ON "issue_tag" (`tag`,`issue_id`);
CREATE INDEX "idx_issue_tag_issue_tag_issue" ON "issue_tag" (`issue_id`);
CREATE INDEX "idx_pto_request_user_id" ON "pto_request" (`user_id`);
CREATE INDEX "idx_pto_request_approved" ON "pto_request" (`approved_date`);
CREATE INDEX "idx_pto_request_pto_request_supervisor_id" ON "pto_request" (`supervisor_id`);
CREATE INDEX "idx_pto_request_pto_request_approved_user_id" ON "pto_request" (`approved_user_id`);
CREATE INDEX "idx_issue_dependency_dependency_id" ON "issue_dependency" (`dependency_id`);
CREATE INDEX "idx_attribute_value_object" ON "attribute_value" (`attribute_id`,`issue_id`);
CREATE INDEX "idx_attribute_issue_type_issue_type" ON "attribute_issue_type" (`issue_type_id`);
CREATE INDEX "idx_attribute_issue_type_attribute_issue_type_attribute_id" ON "attribute_issue_type" (`attribute_id`);
CREATE INDEX "idx_issue_file_missing_index_issue_id" ON "issue_file_missing" (`issue_id`);
CREATE INDEX "idx_issue_file_missing_index_user_id" ON "issue_file_missing" (`user_id`);
CREATE INDEX "idx_issue_file_missing_index_created_on" ON "issue_file_missing" (`created_date`);
CREATE INDEX "idx_user_group_group_id" ON "user_group" (`group_id`);
CREATE INDEX "idx_user_group_group_user_id" ON "user_group" (`user_id`);
CREATE INDEX "idx_issue_update_issue" ON "issue_update" (`issue_id`);
CREATE INDEX "idx_issue_update_user" ON "issue_update" (`user_id`);
CREATE INDEX "idx_issue_backlog_issue_backlog_user_id" ON "issue_backlog" (`user_id`);
CREATE INDEX "idx_issue_backlog_issue_backlog_sprint_id" ON "issue_backlog" (`sprint_id`);
CREATE INDEX "idx_issue_backlog_issue_backlog_type_id" ON "issue_backlog" (`type_id`);
CREATE INDEX "idx_user_note_user_note_user_id" ON "user_note" (`user_id`);
CREATE INDEX "idx_pto_request_manager_pto_request_manager_user_id" ON "pto_request_manager" (`user_id`);
CREATE INDEX "idx_issue_file_index_issue_id" ON "issue_file" (`issue_id`);
CREATE INDEX "idx_issue_file_index_user_id" ON "issue_file" (`user_id`);
CREATE INDEX "idx_issue_file_index_created_on" ON "issue_file" (`created_date`);
CREATE INDEX "idx_issue_checklist_issue_checklist_issue_id" ON "issue_checklist" (`issue_id`);
CREATE INDEX "idx_session_session_user_id" ON "session" (`user_id`);
CREATE INDEX "idx_release_issue_release_issue_release" ON "release_issue" (`release_id`);
CREATE INDEX "idx_poker_vote_poker_vote_project_id" ON "poker_vote" (`project_id`);
CREATE INDEX "idx_issue_sprint_id" ON "issue" (`sprint_id`);
CREATE INDEX "idx_issue_repeat_cycle" ON "issue" (`repeat_cycle`);
CREATE INDEX "idx_issue_due_date" ON "issue" (`due_date`);
CREATE INDEX "idx_issue_type_id" ON "issue" (`type_id`);
CREATE INDEX "idx_issue_parent_id" ON "issue" (`parent_id`);
CREATE INDEX "idx_issue_status" ON "issue" (`status`);
CREATE INDEX "idx_issue_issue_owner_id" ON "issue" (`owner_id`);


CREATE VIEW `user_group_user` AS select `g`.`id` AS `id`,`g`.`group_id` AS `group_id`,`g`.`user_id` AS `user_id`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color`,`u`.`deleted_date` AS `deleted_date`,`g`.`manager` AS `manager` from (`user_group` `g` join `user` `u` on((`g`.`user_id` = `u`.`id`)));

CREATE VIEW `issue_comment_user` AS select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`, `c`.`file_id` as `file_id`, `c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comment` `c` join `user` `u` on((`c`.`user_id` = `u`.`id`)));

CREATE VIEW `issue_comment_detail` AS select `c`.`id` AS `id`, `c`.`issue_id` AS `issue_id`, `c`.`user_id` AS `user_id`, `c`.`text` AS `text`, `c`.`file_id` AS `file_id`, `c`.`created_date` AS `created_date`, `u`.`username` AS `user_username`, `u`.`email` AS `user_email`, `u`.`name` AS `user_name`, `u`.`role` AS `user_role`, `u`.`task_color` AS `user_task_color`, `f`.`filename` AS `file_filename`, `f`.`filesize` AS `file_filesize`, `f`.`content_type` AS `file_content_type`, `f`.`downloads` AS `file_downloads`, `f`.`created_date` AS `file_created_date`, `f`.`deleted_date` AS `file_deleted_date` from ((`issue_comment` `c` join `user` `u` on ((`c`.`user_id` = `u`.`id`))) left join `issue_file` `f` on ((`c`.`file_id` = `f`.`id`)));

CREATE VIEW `issue_detail` AS select `issue`.`id` AS `id`,`issue`.`status` AS `status`,`issue`.`type_id` AS `type_id`,`issue`.`name` AS `name`,`issue`.`description` AS `description`,`issue`.`parent_id` AS `parent_id`,`issue`.`author_id` AS `author_id`,`issue`.`owner_id` AS `owner_id`,`issue`.`priority` AS `priority`,`issue`.`hours_total` AS `hours_total`,`issue`.`hours_remaining` AS `hours_remaining`,`issue`.`hours_spent` AS `hours_spent`,`issue`.`created_date` AS `created_date`,`issue`.`closed_date` AS `closed_date`,`issue`.`deleted_date` AS `deleted_date`,`issue`.`start_date` AS `start_date`,`issue`.`due_date` AS `due_date`, CASE WHEN `issue`.`due_date` IS NULL THEN 0 ELSE 1 END AS `has_due_date`,`issue`.`repeat_cycle` AS `repeat_cycle`,`issue`.`sprint_id` AS `sprint_id`,`sprint`.`name` AS `sprint_name`,`sprint`.`start_date` AS `sprint_start_date`,`sprint`.`end_date` AS `sprint_end_date`,`type`.`name` AS `type_name`,`status`.`name` AS `status_name`,`status`.`closed` AS `status_closed`,`priority`.`id` AS `priority_id`,`priority`.`name` AS `priority_name`,`author`.`username` AS `author_username`,`author`.`name` AS `author_name`,`author`.`email` AS `author_email`,`author`.`task_color` AS `author_task_color`,`owner`.`username` AS `owner_username`,`owner`.`name` AS `owner_name`,`owner`.`email` AS `owner_email`,`owner`.`task_color` AS `owner_task_color` from ((((((`issue` left join `user` `author` on((`issue`.`author_id` = `author`.`id`))) left join `user` `owner` on((`issue`.`owner_id` = `owner`.`id`))) left join `issue_status` `status` on((`issue`.`status` = `status`.`id`))) left join `issue_priority` `priority` on((`issue`.`priority` = `priority`.`value`))) left join `issue_type` `type` on((`issue`.`type_id` = `type`.`id`))) left join `sprint` on((`issue`.`sprint_id` = `sprint`.`id`)));

CREATE VIEW `issue_file_detail` AS select `f`.`id` AS `id`, `f`.`issue_id` AS `issue_id`, `f`.`filename` AS `filename`, `f`.`disk_filename` AS `disk_filename`, `f`.`disk_directory` AS `disk_directory`, `f`.`filesize` AS `filesize`, `f`.`content_type` AS `content_type`, `f`.`digest` AS `digest`, `f`.`downloads` AS `downloads`, `f`.`user_id` AS `user_id`, `f`.`created_date` AS `created_date`, `f`.`deleted_date` AS `deleted_date`, `u`.`username` AS `user_username`, `u`.`email` AS `user_email`, `u`.`name` AS `user_name`, `u`.`task_color` AS `user_task_color` from (`issue_file` `f` join `user` `u` on ((`f`.`user_id` = `u`.`id`)));

CREATE VIEW `issue_update_detail` AS select `i`.`id` AS `id`, `i`.`issue_id` AS `issue_id`, `i`.`user_id` AS `user_id`, `i`.`created_date` AS `created_date`, `u`.`username` AS `user_username`, `u`.`name` AS `user_name`, `u`.`email` AS `user_email`, `i`.`comment_id` AS `comment_id`, `c`.`text` AS `comment_text` from ((`issue_update` `i` join `user` `u` on ((`i`.`user_id` = `u`.`id`))) left join `issue_comment` `c` on ((`i`.`comment_id` = `c`.`id`)));

CREATE VIEW `issue_watcher_user` AS select `w`.`id` AS `watcher_id`,`w`.`issue_id` AS `issue_id`,`u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`u`.`name` AS `name`,`u`.`password` AS `password`,`u`.`role` AS `role`,`u`.`task_color` AS `task_color`,`u`.`created_date` AS `created_date`,`u`.`deleted_date` AS `deleted_date` from (`issue_watcher` `w` join `user` `u` on((`w`.`user_id` = `u`.`id`)));


INSERT INTO `issue_priority` (`id`, `value`, `name`) VALUES
(1, 0, 'Normal'),
(2, 1, 'High'),
(3, -1, 'Low');

INSERT INTO `issue_status` (`id`, `name`, `closed`, `taskboard`, `taskboard_sort`) VALUES
(1, 'New', 0, 2, 1),
(2, 'Active', 0, 2, 2),
(3, 'Completed', 1, 2, 3),
(4, 'On Hold', 0, 1, 4);

INSERT INTO `issue_type` (`id`, `name`) VALUES
(1, 'Task'),
(2, 'Project'),
(3, 'Bug');

INSERT INTO `config` (attribute,value) VALUES ('version','16.09.12');
