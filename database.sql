-- Adminer 3.7.1 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = '+00:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `group`;
CREATE TABLE `group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `group` (`id`, `name`, `deleted_date`) VALUES
(1,	'I.T.',	NULL);

DROP TABLE IF EXISTS `group_user`;
CREATE TABLE `group_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `group_user_id` (`user_id`),
  CONSTRAINT `group_id` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `group_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `group_user` (`id`, `group_id`, `user_id`) VALUES
(5,	1,	1),
(8,	1,	2);

DROP VIEW IF EXISTS `group_user_user`;
CREATE TABLE `group_user_user` (`id` int(10) unsigned, `group_id` int(10) unsigned, `user_id` int(10) unsigned, `user_username` varchar(32), `user_email` varchar(64), `user_name` varchar(32), `user_role` enum('user','admin'), `user_task_color` char(6));


DROP TABLE IF EXISTS `issue`;
CREATE TABLE `issue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL DEFAULT '1',
  `type_id` int(11) NOT NULL DEFAULT '1',
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `closed_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `repeat_cycle` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue` (`id`, `status`, `type_id`, `name`, `description`, `parent_id`, `author_id`, `owner_id`, `created_date`, `closed_date`, `deleted_date`, `due_date`, `repeat_cycle`) VALUES
(1,	1,	1,	'This is a test task',	'This is a task.',	NULL,	1,	1,	'2013-10-18 22:00:00',	NULL,	NULL,	'2013-10-21',	'none'),
(2,	1,	1,	'Finish the task and project pages',	'This is another test task, this time with a much longer description.',	NULL,	1,	1,	'2013-10-19 05:09:36',	NULL,	NULL,	'2013-10-30',	'none'),
(3,	1,	1,	'No due date task',	'This task doesn\'t have a due date.',	NULL,	2,	1,	'2013-10-19 05:09:38',	NULL,	NULL,	NULL,	'none'),
(4,	1,	1,	'Due date task edited',	'This task does have a due date, and it\'s in the past!',	NULL,	2,	1,	'2013-10-19 05:09:40',	NULL,	NULL,	'2013-10-15',	'none'),
(5,	1,	1,	'Test',	'Testing',	1,	1,	1,	'2013-10-23 14:43:55',	NULL,	NULL,	NULL,	'none'),
(6,	1,	1,	'Testing Other Assignee',	'Testy testy testy.',	2,	1,	2,	'2013-12-20 21:47:18',	NULL,	NULL,	'2013-12-23',	'none'),
(7,	1,	2,	'Yay a project.',	'Woooooooo project!!!',	NULL,	1,	1,	'2014-01-06 17:25:55',	NULL,	NULL,	'2014-01-07',	'none'),
(8,	1,	2,	'Testy',	'Testyyyyy\n',	NULL,	1,	1,	'2014-01-06 22:11:59',	NULL,	NULL,	'2014-01-07',	'none');

DROP TABLE IF EXISTS `issues`;
CREATE TABLE `issues` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL DEFAULT '1',
  `type_id` int(11) NOT NULL DEFAULT '1',
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `due_date` date DEFAULT NULL,
  `repeat_cycle` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issues` (`id`, `status`, `type_id`, `name`, `description`, `parent_id`, `author_id`, `owner_id`, `created_date`, `due_date`, `repeat_cycle`) VALUES
(1,	1,	1,	'This is a test task',	'This is a task.',	NULL,	1,	1,	'2013-10-18 22:00:00',	'2013-10-21',	'none'),
(2,	1,	1,	'Finish the task and project pages',	'This is another test task, this time with a much longer description.',	NULL,	1,	1,	'2013-10-19 05:09:36',	'2013-10-30',	'none'),
(3,	1,	1,	'No due date task',	'This task doesn\'t have a due date.',	NULL,	2,	1,	'2013-10-19 05:09:38',	NULL,	'none'),
(4,	1,	1,	'Due date task',	'This task does have a due date, and it\'s in the past!',	NULL,	2,	1,	'2013-10-19 05:09:40',	'2013-10-15',	'none'),
(5,	1,	1,	'Test',	'Testing',	0,	0,	1,	'0000-00-00 00:00:00',	'1970-01-01',	'none'),
(6,	1,	1,	'Testing Other Assignee',	'Testy testy testy.',	2,	1,	2,	'2013-12-20 21:47:18',	'2013-12-23',	'none');

DROP VIEW IF EXISTS `issues_user_data`;
CREATE TABLE `issues_user_data` (`id` int(10) unsigned, `status` int(11), `type_id` int(11), `name` varchar(64), `description` text, `parent_id` int(11), `author_id` int(11), `owner_id` int(11), `created_date` datetime, `due_date` date, `repeat_cycle` enum('none','daily','weekly','monthly'), `author_username` varchar(32), `author_name` varchar(32), `author_email` varchar(64), `owner_username` varchar(32), `owner_name` varchar(32), `owner_email` varchar(64));


DROP TABLE IF EXISTS `issue_comment`;
CREATE TABLE `issue_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`),
  KEY `user` (`user_id`),
  CONSTRAINT `issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user` FOREIGN KEY (`user_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `issue_comment` (`id`, `issue_id`, `user_id`, `text`, `created_date`) VALUES
(1,	4,	1,	'Wow a comment.',	'2014-01-02 15:24:55');

DROP TABLE IF EXISTS `issue_comments`;
CREATE TABLE `issue_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `issue_comments` (`id`, `issue_id`, `user_id`, `text`, `created_date`) VALUES
(1,	4,	1,	'Holy crap a comment.',	'2014-01-02 15:24:55'),
(2,	4,	1,	'Testy',	'2014-01-03 00:42:54'),
(3,	4,	1,	'Sweet :D',	'2014-01-03 00:43:00');

DROP VIEW IF EXISTS `issue_comments_user_data`;
CREATE TABLE `issue_comments_user_data` (`id` int(10) unsigned, `issue_id` int(10) unsigned, `user_id` int(10) unsigned, `text` text, `created_date` datetime, `user_username` varchar(32), `user_email` varchar(64), `user_name` varchar(32), `user_role` enum('user','admin'), `user_task_color` char(6));


DROP VIEW IF EXISTS `issue_comment_user`;
CREATE TABLE `issue_comment_user` (`id` int(10) unsigned, `issue_id` int(10) unsigned, `user_id` int(10) unsigned, `text` text, `created_date` datetime, `user_username` varchar(32), `user_email` varchar(64), `user_name` varchar(32), `user_role` enum('user','admin'), `user_task_color` char(6));


DROP TABLE IF EXISTS `issue_file`;
CREATE TABLE `issue_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `disk_filename` varchar(255) NOT NULL DEFAULT '',
  `filesize` int(11) NOT NULL DEFAULT '0',
  `content_type` varchar(255) DEFAULT '',
  `digest` varchar(40) NOT NULL,
  `downloads` int(11) NOT NULL DEFAULT '0',
  `author_id` int(11) NOT NULL DEFAULT '0',
  `created_on` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `disk_directory` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_issue_id` (`issue_id`),
  KEY `index_attachments_on_author_id` (`author_id`),
  KEY `index_attachments_on_created_on` (`created_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `issue_status`;
CREATE TABLE `issue_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_status` (`id`, `name`, `closed`) VALUES
(1,	'New',	0),
(2,	'Active',	0),
(3,	'Completed',	1),
(4,	'On Hold',	0);

DROP TABLE IF EXISTS `issue_statuses`;
CREATE TABLE `issue_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_statuses` (`id`, `name`, `closed`) VALUES
(1,	'New',	0),
(2,	'Active',	0),
(3,	'Completed',	1),
(4,	'On Hold',	0);

DROP TABLE IF EXISTS `issue_type`;
CREATE TABLE `issue_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_type` (`id`, `name`) VALUES
(1,	'Task'),
(2,	'Project'),
(3,	'Bug');

DROP TABLE IF EXISTS `issue_types`;
CREATE TABLE `issue_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_types` (`id`, `name`) VALUES
(1,	'Task'),
(2,	'Project'),
(3,	'Bug');

DROP TABLE IF EXISTS `issue_update`;
CREATE TABLE `issue_update` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue` (`issue_id`),
  KEY `user` (`user_id`),
  CONSTRAINT `update_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `issue_update` (`id`, `issue_id`, `user_id`, `created_date`) VALUES
(1,	4,	1,	'2014-01-03 23:12:40'),
(2,	7,	1,	'2014-01-06 17:58:19');

DROP TABLE IF EXISTS `issue_update_field`;
CREATE TABLE `issue_update_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_update_id` int(10) unsigned NOT NULL,
  `field` varchar(64) NOT NULL,
  `old_value` text NOT NULL,
  `new_value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_update_field_update_id` (`issue_update_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `issue_update_field` (`id`, `issue_update_id`, `field`, `old_value`, `new_value`) VALUES
(1,	1,	'name',	'Due date task',	'Due date task edited'),
(2,	2,	'name',	'2',	'Yay a project.'),
(3,	2,	'description',	'2',	'Woooooooo project!!!');

DROP VIEW IF EXISTS `issue_update_user`;
CREATE TABLE `issue_update_user` (`id` int(10) unsigned, `issue_id` int(10) unsigned, `user_id` int(10) unsigned, `created_date` datetime, `user_username` varchar(32), `user_name` varchar(32), `user_email` varchar(64));


DROP VIEW IF EXISTS `issue_user`;
CREATE TABLE `issue_user` (`id` int(10) unsigned, `status` int(11), `type_id` int(11), `name` varchar(64), `description` text, `parent_id` int(11), `author_id` int(11), `owner_id` int(11), `created_date` datetime, `deleted_date` datetime, `due_date` date, `repeat_cycle` enum('none','daily','weekly','monthly'), `author_username` varchar(32), `author_name` varchar(32), `author_email` varchar(64), `owner_username` varchar(32), `owner_name` varchar(32), `owner_email` varchar(64));


DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `statuses`;
CREATE TABLE `statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `statuses` (`id`, `name`, `closed`) VALUES
(1,	'New',	0),
(2,	'Active',	0),
(3,	'Completed',	1),
(4,	'On Hold',	0);

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL DEFAULT '1',
  `name` varchar(64) NOT NULL,
  `description` varchar(64) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `repeat_cycle` enum('none','daily','weekly','monthly') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `tasks` (`id`, `status`, `name`, `description`, `project_id`, `user_id`, `due_date`, `repeat_cycle`) VALUES
(1,	1,	'Test',	'This is a task.',	NULL,	1,	'2013-10-21',	'none');

DROP TABLE IF EXISTS `task_comment`;
CREATE TABLE `task_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `task_comments`;
CREATE TABLE `task_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `email` varchar(64) NOT NULL,
  `name` varchar(32) NOT NULL,
  `password` char(60) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `task_color` char(6) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `username`, `email`, `name`, `password`, `role`, `task_color`, `created_date`, `deleted_date`) VALUES
(1,	'alan',	'ahardman@thrivelife.com',	'Alan Hardman',	'$2y$13$rTDxcLCsS/pZRIbLb6vW4.SP/qAn7y/QhWO8WfewsIyhrn9KMLJK2',	'admin',	'b5ed3f',	'2014-01-03 16:23:40',	NULL),
(2,	'shelf',	'shelf@localhost',	'Shelf Testy',	'$2y$13$TDAyoRKvtNyRo08/Ova4YOfCFlXgm7/qKLuw2mW7EHHefcrlRze92',	'user',	'336699',	'2014-01-03 16:23:42',	NULL),
(3,	'test',	'ahardman+test@thrivelife.com',	'Test User',	'$2y$13$SsSLHOoDd9XjV2ao8zvQKuW3.LOsGGTE5HXhF4ftWOvZ5T3FPz8oO',	'user',	'057117',	'2014-01-07 16:52:02',	NULL);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `email` varchar(64) NOT NULL,
  `name` varchar(32) NOT NULL,
  `password` char(60) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `task_color` char(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `users` (`id`, `username`, `email`, `name`, `password`, `role`, `task_color`) VALUES
(1,	'alan',	'ahardman@thrivelife.com',	'Alan Hardman',	'$2y$13$rTDxcLCsS/pZRIbLb6vW4.SP/qAn7y/QhWO8WfewsIyhrn9KMLJK2',	'admin',	'b5ed3f'),
(2,	'shelf',	'shelf@localhost',	'Shelf Testy',	'$2y$13$TDAyoRKvtNyRo08/Ova4YOfCFlXgm7/qKLuw2mW7EHHefcrlRze92',	'admin',	'336699');

DROP TABLE IF EXISTS `watcher`;
CREATE TABLE `watcher` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `watchers`;
CREATE TABLE `watchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `group_user_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `group_user_user` AS (select `g`.`id` AS `id`,`g`.`group_id` AS `group_id`,`g`.`user_id` AS `user_id`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`group_user` `g` join `user` `u` on((`g`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issues_user_data`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issues_user_data` AS (select `issue`.`id` AS `id`,`issue`.`status` AS `status`,`issue`.`type_id` AS `type_id`,`issue`.`name` AS `name`,`issue`.`description` AS `description`,`issue`.`parent_id` AS `parent_id`,`issue`.`author_id` AS `author_id`,`issue`.`owner_id` AS `owner_id`,`issue`.`created_date` AS `created_date`,`issue`.`due_date` AS `due_date`,`issue`.`repeat_cycle` AS `repeat_cycle`,`author`.`username` AS `author_username`,`author`.`name` AS `author_name`,`author`.`email` AS `author_email`,`owner`.`username` AS `owner_username`,`owner`.`name` AS `owner_name`,`owner`.`email` AS `owner_email` from ((`issues` `issue` left join `users` `author` on((`issue`.`author_id` = `author`.`id`))) left join `users` `owner` on((`issue`.`owner_id` = `owner`.`id`))));

DROP TABLE IF EXISTS `issue_comments_user_data`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_comments_user_data` AS (select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`,`c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comments` `c` join `users` `u` on((`c`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_comment_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_comment_user` AS (select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`,`c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comment` `c` join `user` `u` on((`c`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_update_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_update_user` AS (select `i`.`id` AS `id`,`i`.`issue_id` AS `issue_id`,`i`.`user_id` AS `user_id`,`i`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`name` AS `user_name`,`u`.`email` AS `user_email` from (`issue_update` `i` join `user` `u` on((`i`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_user` AS (select `issue`.`id` AS `id`,`issue`.`status` AS `status`,`issue`.`type_id` AS `type_id`,`issue`.`name` AS `name`,`issue`.`description` AS `description`,`issue`.`parent_id` AS `parent_id`,`issue`.`author_id` AS `author_id`,`issue`.`owner_id` AS `owner_id`,`issue`.`created_date` AS `created_date`,`issue`.`deleted_date` AS `deleted_date`,`issue`.`due_date` AS `due_date`,`issue`.`repeat_cycle` AS `repeat_cycle`,`author`.`username` AS `author_username`,`author`.`name` AS `author_name`,`author`.`email` AS `author_email`,`owner`.`username` AS `owner_username`,`owner`.`name` AS `owner_name`,`owner`.`email` AS `owner_email` from ((`issue` left join `user` `author` on((`issue`.`author_id` = `author`.`id`))) left join `user` `owner` on((`issue`.`owner_id` = `owner`.`id`))));

-- 2014-01-10 07:54:13
