SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`username` varchar(32) DEFAULT NULL,
	`email` varchar(64) DEFAULT NULL,
	`name` varchar(32) NOT NULL,
	`password` char(40) DEFAULT NULL,
	`salt` char(32) DEFAULT NULL,
	`reset_token` CHAR(96) NULL,
	`role` enum('user','admin','group') NOT NULL DEFAULT 'user',
	`rank` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`task_color` char(6) DEFAULT NULL,
	`theme` varchar(64) DEFAULT NULL,
	`language` varchar(5) DEFAULT NULL,
	`avatar_filename` varchar(64) DEFAULT NULL,
	`options` blob NULL,
	`api_key` varchar(40) NULL,
	`api_visible` tinyint(1) unsigned NOT NULL DEFAULT '1',
	`created_date` datetime NOT NULL,
	`deleted_date` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `username` (`username`),
	UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `user_group` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int(10) unsigned NOT NULL,
	`group_id` int(10) unsigned NOT NULL,
	`manager` tinyint(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `group_id` (`group_id`),
	KEY `group_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue`;
CREATE TABLE `issue` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`status` int(10) unsigned NOT NULL DEFAULT '1',
	`type_id` int(10) unsigned NOT NULL DEFAULT '1',
	`name` varchar(255) NOT NULL,
	`size_estimate` VARCHAR(20) NULL,
	`description` text NOT NULL,
	`parent_id` int(10) unsigned DEFAULT NULL,
	`author_id` int(10) unsigned NOT NULL,
	`owner_id` int(10) unsigned DEFAULT NULL,
	`priority` int(10) NOT NULL DEFAULT '0',
	`hours_total` double unsigned DEFAULT NULL,
	`hours_remaining` double unsigned DEFAULT NULL,
	`hours_spent` double unsigned DEFAULT NULL,
	`created_date` datetime NOT NULL,
	`closed_date` datetime DEFAULT NULL,
	`deleted_date` datetime DEFAULT NULL,
	`start_date` date DEFAULT NULL,
	`due_date` date DEFAULT NULL,
	`repeat_cycle` varchar(20) NULL,
	`sprint_id` int(10) unsigned DEFAULT NULL,
	`due_date_sprint` tinyint(1) unsigned DEFAULT 0 NOT NULL,
	PRIMARY KEY (`id`),
	KEY `sprint_id` (`sprint_id`),
	KEY `repeat_cycle` (`repeat_cycle`),
	KEY `due_date` (`due_date`),
	KEY `type_id` (`type_id`),
	KEY `parent_id` (`parent_id`),
	CONSTRAINT `issue_type_id` FOREIGN KEY (`type_id`) REFERENCES `issue_type`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT `issue_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `issue_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `issue_author_id` FOREIGN KEY (`author_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT `issue_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `issue_priority` FOREIGN KEY (`priority`) REFERENCES `issue_priority`(`value`) ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT `issue_status` FOREIGN KEY (`status`) REFERENCES `issue_status`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_backlog`;
CREATE TABLE `issue_backlog` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`sprint_id` int(10) unsigned DEFAULT NULL,
	`issues` blob NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `issue_backlog_sprint_id` (`sprint_id`),
	CONSTRAINT `issue_backlog_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_comment`;
CREATE TABLE `issue_comment` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_id` int(10) unsigned NOT NULL,
	`user_id` int(10) unsigned NOT NULL,
	`text` text NOT NULL,
	`file_id` int(10) unsigned NULL,
	`created_date` datetime NOT NULL,
	PRIMARY KEY (`id`),
	KEY `issue_id` (`issue_id`),
	KEY `user` (`user_id`),
	CONSTRAINT `comment_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `comment_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_file`;
CREATE TABLE `issue_file` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_id` int(10) unsigned NOT NULL,
	`filename` varchar(255) NOT NULL DEFAULT '',
	`disk_filename` varchar(255) NOT NULL DEFAULT '',
	`disk_directory` varchar(255) DEFAULT NULL,
	`filesize` int(11) NOT NULL DEFAULT '0',
	`content_type` varchar(255) DEFAULT '',
	`digest` varchar(40) NOT NULL,
	`downloads` int(11) NOT NULL DEFAULT '0',
	`user_id` int(10) unsigned NOT NULL DEFAULT '0',
	`created_date` datetime NOT NULL,
	`deleted_date` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `index_issue_id` (`issue_id`),
	KEY `index_user_id` (`user_id`),
	KEY `index_created_on` (`created_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_priority`;
CREATE TABLE `issue_priority` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`value` int(10) NOT NULL,
	`name` varchar(64) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `priority` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `issue_priority` (`id`, `value`, `name`) VALUES
(1, 0, 'Normal'),
(2, 1, 'High'),
(3, -1, 'Low');

DROP TABLE IF EXISTS `issue_status`;
CREATE TABLE `issue_status` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(32) NOT NULL,
	`closed` tinyint(1) NOT NULL DEFAULT '0',
	`taskboard` tinyint(1) NOT NULL DEFAULT '1',
	`taskboard_sort` INT UNSIGNED NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `issue_status` (`id`, `name`, `closed`, `taskboard`, `taskboard_sort`) VALUES
(1, 'New', 0, 2, 1),
(2, 'Active', 0, 2, 2),
(3, 'Completed', 1, 2, 3),
(4, 'On Hold', 0, 1, 4);

DROP TABLE IF EXISTS `issue_type`;
CREATE TABLE `issue_type` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(32) NOT NULL,
	`role` ENUM('task','project','bug') DEFAULT 'task' NOT NULL,
	`default_description` text NULL,
	PRIMARY KEY (`id`),
	KEY `issue_type_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `issue_type` (`id`, `name`, `role`) VALUES
(1, 'Task', 'task'),
(2, 'Project', 'project'),
(3, 'Bug', 'bug');

DROP TABLE IF EXISTS `issue_update`;
CREATE TABLE `issue_update` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_id` int(10) unsigned NOT NULL,
	`user_id` int(10) unsigned NOT NULL,
	`created_date` datetime NOT NULL,
	`comment_id` int(10) unsigned DEFAULT NULL,
	`notify` TINYINT(1) UNSIGNED NULL,
	PRIMARY KEY (`id`),
	KEY `issue` (`issue_id`),
	KEY `user` (`user_id`),
	CONSTRAINT `update_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_update_field`;
CREATE TABLE `issue_update_field` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_update_id` int(10) unsigned NOT NULL,
	`field` varchar(64) NOT NULL,
	`old_value` text NOT NULL,
	`new_value` text NOT NULL,
	PRIMARY KEY (`id`),
	KEY `issue_update_field_update_id` (`issue_update_id`),
	CONSTRAINT `issue_update_field_update` FOREIGN KEY (`issue_update_id`) REFERENCES `issue_update` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_watcher`;
CREATE TABLE `issue_watcher` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_id` int(10) unsigned NOT NULL,
	`user_id` int(10) unsigned NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `unique_watch` (`issue_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_tag`;
CREATE TABLE `issue_tag`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tag` VARCHAR(60) NOT NULL,
	`issue_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `issue_tag_tag` (`tag`, `issue_id`),
	CONSTRAINT `issue_tag_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `issue_dependency`;
CREATE TABLE `issue_dependency` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`issue_id` int(10) unsigned NOT NULL,
	`dependency_id` int(10) unsigned NOT NULL,
	`dependency_type` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `issue_id_dependency_id` (`issue_id`,`dependency_id`),
	KEY `dependency_id` (`dependency_id`),
	CONSTRAINT `issue_dependency_ibfk_2` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `issue_dependency_ibfk_3` FOREIGN KEY (`dependency_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sprint`;
CREATE TABLE `sprint` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(60) NOT NULL,
	`start_date` date NOT NULL,
	`end_date` date NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP VIEW IF EXISTS `user_group_user`;
CREATE VIEW `user_group_user` AS (select `g`.`id` AS `id`,`g`.`group_id` AS `group_id`,`g`.`user_id` AS `user_id`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color`,`u`.`deleted_date` AS `deleted_date`,`g`.`manager` AS `manager` from (`user_group` `g` join `user` `u` on((`g`.`user_id` = `u`.`id`))));

DROP VIEW IF EXISTS `issue_comment_user`;
CREATE VIEW `issue_comment_user` AS (select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`, `c`.`file_id` as `file_id`, `c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comment` `c` join `user` `u` on((`c`.`user_id` = `u`.`id`))));

DROP VIEW IF EXISTS `issue_comment_detail`;
CREATE VIEW `issue_comment_detail` AS (select `c`.`id` AS `id`, `c`.`issue_id` AS `issue_id`, `c`.`user_id` AS `user_id`, `c`.`text` AS `text`, `c`.`file_id` AS `file_id`, `c`.`created_date` AS `created_date`, `u`.`username` AS `user_username`, `u`.`email` AS `user_email`, `u`.`name` AS `user_name`, `u`.`role` AS `user_role`, `u`.`task_color` AS `user_task_color`, `f`.`filename` AS `file_filename`, `f`.`filesize` AS `file_filesize`, `f`.`content_type` AS `file_content_type`, `f`.`downloads` AS `file_downloads`, `f`.`created_date` AS `file_created_date`, `f`.`deleted_date` AS `file_deleted_date`, `i`.`deleted_date` AS `issue_deleted_date` from `issue_comment` `c` join `user` `u` on `c`.`user_id` = `u`.`id` left join `issue_file` `f` on `c`.`file_id` = `f`.`id` JOIN `issue` `i` ON `i`.`id` = `c`.`issue_id`);

DROP VIEW IF EXISTS `issue_detail`;
CREATE VIEW `issue_detail` AS
SELECT
	`issue`.`id` AS `id`,
	`issue`.`status` AS `status`,
	`issue`.`type_id` AS `type_id`,
	`issue`.`name` AS `name`,
	`issue`.`size_estimate` AS `size_estimate`,
	`issue`.`description` AS `description`,
	`issue`.`parent_id` AS `parent_id`,
	`issue`.`author_id` AS `author_id`,
	`issue`.`owner_id` AS `owner_id`,
	`issue`.`priority` AS `priority`,
	`issue`.`hours_total` AS `hours_total`,
	`issue`.`hours_remaining` AS `hours_remaining`,
	`issue`.`hours_spent` AS `hours_spent`,
	`issue`.`created_date` AS `created_date`,
	`issue`.`closed_date` AS `closed_date`,
	`issue`.`deleted_date` AS `deleted_date`,
	`issue`.`start_date` AS `start_date`,
	`issue`.`due_date` AS `due_date`,
	ISNULL(`issue`.`due_date`) AS `has_due_date`,
	`issue`.`repeat_cycle` AS `repeat_cycle`,
	`issue`.`sprint_id` AS `sprint_id`,
	`issue`.`due_date_sprint` AS `due_date_sprint`,
	`sprint`.`name` AS `sprint_name`,
	`sprint`.`start_date` AS `sprint_start_date`,
	`sprint`.`end_date` AS `sprint_end_date`,
	`type`.`name` AS `type_name`,
	`status`.`name` AS `status_name`,
	`status`.`closed` AS `status_closed`,
	`priority`.`id` AS `priority_id`,
	`priority`.`name` AS `priority_name`,
	`author`.`username` AS `author_username`,
	`author`.`name` AS `author_name`,
	`author`.`email` AS `author_email`,
	`author`.`task_color` AS `author_task_color`,
	`owner`.`username` AS `owner_username`,
	`owner`.`name` AS `owner_name`,
	`owner`.`email` AS `owner_email`,
	`owner`.`task_color` AS `owner_task_color`,
	`parent`.`name` AS `parent_name`
FROM `issue`
LEFT JOIN `user` `author` on`issue`.`author_id` = `author`.`id`
LEFT JOIN `user` `owner` on`issue`.`owner_id` = `owner`.`id`
LEFT JOIN `issue_status` `status` on`issue`.`status` = `status`.`id`
LEFT JOIN `issue_priority` `priority` on`issue`.`priority` = `priority`.`value`
LEFT JOIN `issue_type` `type` on`issue`.`type_id` = `type`.`id`
LEFT JOIN `sprint` on`issue`.`sprint_id` = `sprint`.`id`
LEFT JOIN `issue` `parent` ON `issue`.`parent_id` = `parent`.`id`;

DROP VIEW IF EXISTS `issue_file_detail`;
CREATE VIEW `issue_file_detail` AS (select `f`.`id` AS `id`, `f`.`issue_id` AS `issue_id`, `f`.`filename` AS `filename`, `f`.`disk_filename` AS `disk_filename`, `f`.`disk_directory` AS `disk_directory`, `f`.`filesize` AS `filesize`, `f`.`content_type` AS `content_type`, `f`.`digest` AS `digest`, `f`.`downloads` AS `downloads`, `f`.`user_id` AS `user_id`, `f`.`created_date` AS `created_date`, `f`.`deleted_date` AS `deleted_date`, `u`.`username` AS `user_username`, `u`.`email` AS `user_email`, `u`.`name` AS `user_name`, `u`.`task_color` AS `user_task_color` from (`issue_file` `f` join `user` `u` on ((`f`.`user_id` = `u`.`id`))));

DROP VIEW IF EXISTS `issue_update_detail`;
CREATE VIEW `issue_update_detail` AS (select `i`.`id` AS `id`, `i`.`issue_id` AS `issue_id`, `i`.`user_id` AS `user_id`, `i`.`created_date` AS `created_date`, `i`.`notify` AS `notify`, `u`.`username` AS `user_username`, `u`.`name` AS `user_name`, `u`.`email` AS `user_email`, `i`.`comment_id` AS `comment_id`, `c`.`text` AS `comment_text` from ((`issue_update` `i` join `user` `u` on ((`i`.`user_id` = `u`.`id`))) left join `issue_comment` `c` on ((`i`.`comment_id` = `c`.`id`))));

DROP VIEW IF EXISTS `issue_watcher_user`;
CREATE VIEW `issue_watcher_user` AS (select `w`.`id` AS `watcher_id`,`w`.`issue_id` AS `issue_id`,`u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`u`.`name` AS `name`,`u`.`password` AS `password`,`u`.`role` AS `role`,`u`.`task_color` AS `task_color`,`u`.`created_date` AS `created_date`,`u`.`deleted_date` AS `deleted_date` from (`issue_watcher` `w` join `user` `u` on((`w`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `session`;
CREATE TABLE `session`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`token` VARBINARY(64) NOT NULL,
	`ip` VARBINARY(39) NOT NULL,
	`user_id` INT UNSIGNED NOT NULL,
	`created` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `session_token` (`token`, `ip`),
	KEY `session_user_id` (`user_id`),
	CONSTRAINT `session_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
	`id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`attribute` varchar(255) NULL,
	`value` varchar(255) NULL,
	UNIQUE KEY `attribute` (`attribute`)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config` (`attribute`,`value`) VALUES ('security.reset_ttl', '86400');
INSERT INTO `config` (`attribute`,`value`) VALUES ('security.file_blacklist', '/\.(ph(p([3457s]|\-s)?|t|tml)|aspx?|shtml|exe|dll)$/i');
INSERT INTO `config` (`attribute`, `value`) VALUES ('version', '21.03.18');
