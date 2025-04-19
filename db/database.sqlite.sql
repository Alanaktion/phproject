DROP TABLE IF EXISTS user;
CREATE TABLE user (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`username` TEXT DEFAULT NULL,
	`email` TEXT DEFAULT NULL,
	`name` TEXT NOT NULL,
	`password` CHAR(40) DEFAULT NULL,
	`salt` CHAR(32) DEFAULT NULL,
	`reset_token` CHAR(96) DEFAULT NULL,
	`role` TEXT NOT NULL DEFAULT 'user',
	`rank` INTEGER NOT NULL DEFAULT 0,
	`task_color` CHAR(6) DEFAULT NULL,
	`theme` TEXT DEFAULT NULL,
	`language` TEXT DEFAULT NULL,
	`avatar_filename` TEXT DEFAULT NULL,
	`options` TEXT DEFAULT NULL,
	`api_key` TEXT DEFAULT NULL,
	`api_visible` INTEGER NOT NULL DEFAULT 1,
	`created_date` DATETIME NOT NULL,
	`deleted_date` DATETIME DEFAULT NULL,
	UNIQUE (username),
	UNIQUE (email)
);

DROP TABLE IF EXISTS user_group;
CREATE TABLE user_group (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`user_id` INTEGER NOT NULL,
	`group_id` INTEGER NOT NULL,
	`manager` BOOLEAN NOT NULL DEFAULT 0,
	FOREIGN KEY (group_id) REFERENCES user (id),
	FOREIGN KEY (user_id) REFERENCES user (id)
);

DROP TABLE IF EXISTS issue;
CREATE TABLE issue (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`status` INTEGER NOT NULL DEFAULT 1,
	`type_id` INTEGER NOT NULL DEFAULT 1,
	`name` TEXT NOT NULL,
	`size_estimate` VARCHAR(20) DEFAULT NULL,
	`description` TEXT NOT NULL,
	`parent_id` INTEGER DEFAULT NULL,
	`author_id` INTEGER NOT NULL,
	`owner_id` INTEGER DEFAULT NULL,
	`priority` INTEGER NOT NULL DEFAULT 0,
	`hours_total` DOUBLE DEFAULT NULL,
	`hours_remaining` DOUBLE DEFAULT NULL,
	`hours_spent` DOUBLE DEFAULT NULL,
	`created_date` DATETIME NOT NULL,
	`closed_date` DATETIME DEFAULT NULL,
	`deleted_date` DATETIME DEFAULT NULL,
	`start_date` DATE DEFAULT NULL,
	`due_date` DATE DEFAULT NULL,
	`repeat_cycle` TEXT DEFAULT NULL,
	`sprint_id` INTEGER DEFAULT NULL,
	`due_date_sprint` INTEGER NOT NULL DEFAULT 0,
	FOREIGN KEY (type_id) REFERENCES issue_type(id),
	FOREIGN KEY (parent_id) REFERENCES issue(id),
	FOREIGN KEY (sprint_id) REFERENCES sprint(id),
	FOREIGN KEY (author_id) REFERENCES user(id),
	FOREIGN KEY (owner_id) REFERENCES user(id),
	FOREIGN KEY (priority) REFERENCES issue_priority(value),
	FOREIGN KEY (status) REFERENCES issue_status(id)
);

DROP TABLE IF EXISTS issue_backlog;
CREATE TABLE issue_backlog (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`sprint_id` INTEGER DEFAULT NULL,
	`issues` TEXT DEFAULT NULL,
	FOREIGN KEY (sprint_id) REFERENCES sprint(id)
);

DROP TABLE IF EXISTS issue_comment;
CREATE TABLE issue_comment (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_id` INTEGER NOT NULL,
	`user_id` INTEGER NOT NULL,
	`text` TEXT NOT NULL,
	`file_id` INTEGER DEFAULT NULL,
	`created_date` DATETIME NOT NULL,
	FOREIGN KEY (issue_id) REFERENCES issue(id),
	FOREIGN KEY (user_id) REFERENCES user(id)
);

DROP TABLE IF EXISTS issue_file;
CREATE TABLE issue_file (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_id` INTEGER NOT NULL,
	`filename` TEXT DEFAULT '',
	`disk_filename` TEXT DEFAULT '',
	`disk_directory` TEXT DEFAULT NULL,
	`filesize` INTEGER NOT NULL DEFAULT 0,
	`content_type` TEXT DEFAULT '',
	`digest` CHAR(40) NOT NULL,
	`downloads` INTEGER NOT NULL DEFAULT 0,
	`user_id` INTEGER NOT NULL DEFAULT 0,
	`created_date` DATETIME NOT NULL,
	`deleted_date` DATETIME DEFAULT NULL
);

DROP TABLE IF EXISTS issue_priority;
CREATE TABLE issue_priority (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`value` INTEGER NOT NULL,
	`name` TEXT NOT NULL,
	UNIQUE (value)
);

INSERT INTO `issue_priority` (`id`, `value`, `name`) VALUES
(1, 0, 'Normal'),
(2, 1, 'High'),
(3, -1, 'Low');

DROP TABLE IF EXISTS issue_status;
CREATE TABLE issue_status (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`name` TEXT NOT NULL,
	`closed` BOOLEAN NOT NULL DEFAULT 0,
	`taskboard` BOOLEAN NOT NULL DEFAULT 1,
	`taskboard_sort` INT DEFAULT NULL
);

INSERT INTO `issue_status` (`id`, `name`, `closed`, `taskboard`, `taskboard_sort`) VALUES
(1, 'New', 0, 2, 1),
(2, 'Active', 0, 2, 2),
(3, 'Completed', 1, 2, 3),
(4, 'On Hold', 0, 1, 4);

DROP TABLE IF EXISTS issue_type;
CREATE TABLE issue_type (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`name` TEXT NOT NULL,
	`role` TEXT DEFAULT 'task' NOT NULL,
	`default_description` TEXT DEFAULT NULL
);

INSERT INTO `issue_type` (`id`, `name`, `role`) VALUES
(1, 'Task', 'task'),
(2, 'Project', 'project'),
(3, 'Bug', 'bug');

DROP TABLE IF EXISTS issue_update;
CREATE TABLE issue_update (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_id` INTEGER NOT NULL,
	`user_id` INTEGER NOT NULL,
	`created_date` DATETIME NOT NULL,
	`comment_id` INTEGER DEFAULT NULL,
	`notify` BOOLEAN DEFAULT 0
);

DROP TABLE IF EXISTS issue_update_field;
CREATE TABLE issue_update_field (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_update_id` INTEGER NOT NULL,
	`field` TEXT NOT NULL,
	`old_value` TEXT NOT NULL,
	`new_value` TEXT NOT NULL
);

DROP TABLE IF EXISTS issue_watcher;
CREATE TABLE issue_watcher (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_id` INTEGER NOT NULL,
	`user_id` INTEGER NOT NULL,
	UNIQUE (issue_id, user_id)
);

DROP TABLE IF EXISTS issue_tag;
CREATE TABLE issue_tag (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`tag` TEXT NOT NULL,
	`issue_id` INTEGER NOT NULL
);

INSERT INTO `issue_tag` (`id`, `tag`, `issue_id`) VALUES
(1, 'tag', 1);

DROP TABLE IF EXISTS issue_dependency;
CREATE TABLE issue_dependency (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`issue_id` INTEGER NOT NULL,
	`dependency_id` INTEGER NOT NULL,
	`dependency_type` TEXT NOT NULL,
	UNIQUE (issue_id, dependency_id)
);

DROP TABLE IF EXISTS sprint;
CREATE TABLE sprint (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`name` TEXT NOT NULL,
	`start_date` DATE NOT NULL,
	`end_date` DATE NOT NULL
);

DROP VIEW IF EXISTS `user_group_user`;
CREATE VIEW `user_group_user` AS
SELECT `g`.`id`,
`g`.`group_id`,
`g`.`user_id`,
`u`.`username` AS `user_username`,
`u`.`email` AS `user_email`,
`u`.`name` AS `user_name`,
`u`.`role` AS `user_role`,
`u`.`task_color` AS `user_task_color`,
`u`.`deleted_date`,
`g`.`manager` FROM user_group g JOIN user u ON `g`.`user_id` = `u`.`id`;

DROP VIEW IF EXISTS `issue_comment_user`;
CREATE VIEW `issue_comment_user` AS
SELECT `c`.`id`,
`c`.`issue_id`,
`c`.`user_id`,
`c`.`text`,
`c`.`file_id` as `file_id`,
`c`.`created_date`,
`u`.`username` AS `user_username`,
`u`.`email` AS `user_email`,
`u`.`name` AS `user_name`,
`u`.`role` AS `user_role`,
`u`.`task_color` AS `user_task_color` FROM issue_comment c JOIN user u ON `c`.`user_id` = `u`.`id`;

DROP VIEW IF EXISTS `issue_comment_detail`;
CREATE VIEW `issue_comment_detail` AS
SELECT `c`.`id`,
`c`.`issue_id`,
`c`.`user_id`,
`c`.`text`,
`c`.`file_id`,
`c`.`created_date`,
`u`.`username` AS `user_username`,
`u`.`email` AS `user_email`,
`u`.`name` AS `user_name`,
`u`.`role` AS `user_role`,
`u`.`task_color` AS `user_task_color`,
`f`.`filename` AS `file_filename`,
`f`.`filesize` AS `file_filesize`,
`f`.`content_type` AS `file_content_type`,
`f`.`downloads` AS `file_downloads`,
`f`.`created_date` AS `file_created_date`,
`f`.`deleted_date` AS `file_deleted_date`,
`i`.`deleted_date` AS `issue_deleted_date` FROM `issue_comment` `c` JOIN `user` `u` ON `c`.`user_id` = `u`.`id` LEFT JOIN `issue_file` `f` ON `c`.`file_id` = `f`.`id` JOIN `issue` `i` ON `i`.`id` = `c`.`issue_id`;

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
	`issue`.`due_date` IS NULL AS `has_due_date`,
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
LEFT JOIN `user` `author` ON `issue`.`author_id` = `author`.`id`
LEFT JOIN `user` `owner` ON `issue`.`owner_id` = `owner`.`id`
LEFT JOIN `issue_status` `status` ON `issue`.`status` = `status`.`id`
LEFT JOIN `issue_priority` `priority` ON `issue`.`priority` = `priority`.`value`
LEFT JOIN issue_type type ON issue.type_id = type.id
LEFT JOIN sprint on issue.sprint_id = sprint.id
LEFT JOIN issue parent ON issue.parent_id = parent.id;

DROP VIEW IF EXISTS issue_file_detail;
CREATE VIEW issue_file_detail AS
SELECT `f`.`id`,
`f`.`issue_id`,
`f`.`filename`,
`f`.`disk_filename`,
`f`.`disk_directory`,
`f`.`filesize`,
`f`.`content_type`,
`f`.`digest`,
`f`.`downloads`,
`f`.`user_id`,
`f`.`created_date`,
`f`.`deleted_date`,
`u`.`username` AS `user_username`,
`u`.`email` AS `user_email`,
`u`.`name` AS `user_name` FROM issue_file f JOIN user u ON f.user_id = `u`.`id`;

DROP VIEW IF EXISTS issue_update_detail;
CREATE VIEW issue_update_detail AS
SELECT `i`.`id`,
`i`.`issue_id`,
`i`.`user_id`,
`i`.`created_date`,
`i`.`notify`,
`u`.`username` AS `user_username`,
`u`.`name` AS `user_name`,
`u`.`email` AS `user_email`,
`i`.`comment_id`,
`c`.`text` AS `comment_text` FROM issue_update i JOIN user u ON i.user_id = `u`.`id` LEFT JOIN issue_comment c ON i.comment_id = c.id;

DROP VIEW IF EXISTS issue_watcher_user;
CREATE VIEW issue_watcher_user AS
SELECT `w`.`id` AS `watcher_id`,
`w`.`issue_id`,
`u`.`id`,
`u`.`username`,
`u`.`email`,
`u`.`name`,
`u`.`password`,
`u`.`role`,
`u`.`task_color`,
`u`.`created_date`,
`u`.`deleted_date` FROM issue_watcher w JOIN user u ON w.user_id = u.id;

DROP TABLE IF EXISTS session;
CREATE TABLE session (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`token` TEXT NOT NULL,
	`ip` TEXT NOT NULL,
	`user_id` INTEGER NOT NULL,
	`created` DATETIME NOT NULL
);

DROP TABLE IF EXISTS config;
CREATE TABLE config (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	`attribute` TEXT NOT NULL,
	`value` TEXT NOT NULL,
	UNIQUE (attribute)
);

INSERT INTO `config` (`attribute`, `value`) VALUES ('security.reset_ttl', '86400');
INSERT INTO `config` (`attribute`, `value`) VALUES ('security.file_blacklist', '/\.(ph(p([3457s]|\-s)?|t|tml)|aspx?|shtml|exe|dll)$/i');
INSERT INTO `config` (`attribute`, `value`) VALUES ('version', '21.03.18');
