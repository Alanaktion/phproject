SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = '-07:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE `phproject` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `phproject`;

DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `group_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `manager` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `group_user_id` (`user_id`),
  CONSTRAINT `group_id` FOREIGN KEY (`group_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `group_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP VIEW IF EXISTS `user_group_user`;
CREATE TABLE `user_group_user` (`id` int(10) unsigned, `group_id` int(10) unsigned, `user_id` int(10) unsigned, `user_username` varchar(32), `user_email` varchar(64), `user_name` varchar(32), `user_role` enum('user','admin','group'), `user_task_color` char(6), `deleted_date` datetime);

DROP TABLE IF EXISTS `issue`;
CREATE TABLE `issue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL DEFAULT '1',
  `type_id` int(11) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `author_id` int(11) unsigned NOT NULL,
  `owner_id` int(11) unsigned DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT '0',
  `hours_total` double unsigned DEFAULT NULL,
  `hours_remaining` double unsigned DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `closed_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `repeat_cycle` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  `sprint_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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


DROP VIEW IF EXISTS `issue_comment_user`;
CREATE TABLE `issue_comment_user` (`id` int(10) unsigned, `issue_id` int(10) unsigned, `user_id` int(10) unsigned, `text` text, `created_date` datetime, `user_username` varchar(32), `user_email` varchar(64), `user_name` varchar(32), `user_role` enum('user','admin','group'), `user_task_color` char(6));

DROP VIEW IF EXISTS `issue_detail`;
CREATE TABLE `issue_detail` (
  `id` int(10) unsigned, `status` int(11),
  `type_id` int(11) unsigned,
  `name` varchar(255),
  `description` text,
  `parent_id` int(11) unsigned,
  `author_id` int(11) unsigned,
  `owner_id` int(11) unsigned,
  `priority` int(11) NOT NULL DEFAULT '0',
  `hours_total` double unsigned DEFAULT NULL,
  `hours_remaining` double unsigned DEFAULT NULL,
  `created_date` datetime,
  `closed_date` datetime,
  `deleted_date` datetime,
  `due_date` date,
  `has_due_date` int(1),
  `repeat_cycle` enum('none','daily','weekly','monthly'),
  `sprint_id` int(10) unsigned,
  `type_name` varchar(32),
  `status_name` varchar(32),
  `status_closed` tinyint(1),
  `priority_id` int(10) unsigned,
  `priority_name` varchar(64),
  `author_username` varchar(32),
  `author_name` varchar(32),
  `author_email` varchar(64),
  `author_task_color` char(6),
  `owner_username` varchar(32),
  `owner_name` varchar(32),
  `owner_email` varchar(64),
  `owner_task_color` char(6)
);

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
  `created_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_issue_id` (`issue_id`),
  KEY `index_user_id` (`user_id`),
  KEY `index_created_on` (`created_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `issue_priority`;
CREATE TABLE `issue_priority` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` int(10) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `issue_priority` (`id`, `value`, `name`) VALUES
(1, 0,  'Normal'),
(2, 1,  'High'),
(3, -1,  'Low');

DROP TABLE IF EXISTS `issue_status`;
CREATE TABLE `issue_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_status` (`id`, `name`, `closed`) VALUES
(1, 'New',  0),
(2, 'Active',   0),
(3, 'Completed',    1),
(4, 'On Hold',  0);

DROP TABLE IF EXISTS `issue_type`;
CREATE TABLE `issue_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `issue_type` (`id`, `name`) VALUES
(1, 'Task'),
(2, 'Project'),
(3, 'Bug');

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

DROP VIEW IF EXISTS `issue_update_user`;
CREATE TABLE `issue_update_user` (`id` int(10) unsigned, `issue_id` int(10) unsigned, `user_id` int(10) unsigned, `created_date` datetime, `user_username` varchar(32), `user_name` varchar(32), `user_email` varchar(64));

DROP TABLE IF EXISTS `issue_watcher`;
CREATE TABLE `issue_watcher` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_watch` (`issue_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP VIEW IF EXISTS `issue_watcher_user`;
CREATE TABLE `issue_watcher_user` (`watcher_id` int(10) unsigned, `issue_id` int(10) unsigned, `id` int(10) unsigned, `username` varchar(32), `email` varchar(64), `name` varchar(32), `password` char(40), `role` enum('user','admin','group'), `task_color` char(6), `created_date` datetime, `deleted_date` datetime);

DROP TABLE IF EXISTS `sprint`;
CREATE TABLE `sprint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `name` varchar(32) NOT NULL,
  `password` char(40) DEFAULT NULL,
  `salt` char(32) DEFAULT NULL,
  `role` enum('user','admin','group') NOT NULL DEFAULT 'user',
  `task_color` char(6) DEFAULT NULL,
  `theme` varchar(64) DEFAULT NULL,
  `avatar_filename` varchar(64) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `username`, `email`, `name`, `password`, `salt`, `role`, `task_color`, `theme`, `avatar_filename`, `created_date`, `deleted_date`) VALUES
(1, 'ahardman', 'ahardman@thrivelife.com',  'Alan Hardman', '7b9e164b5ec6bbd1208f092d0f88ca8cdcf5eaad', '2cbc81d479e7664070d2f1657787904d', 'admin',    'b5ed3f',   '/css/bootstrap-flatly.min.css',    '1-356a.png',   '2014-01-03 16:23:40',  NULL),
(2, 'shelf',    'ahardman+tron@thrivelife.com', 'Shalf Tasty',  'b76614097e5860a207dd6ca69de4fadef8915d9c', '18d0fa4a469ab43a4f629b03039f2d18', 'admin',    '336699',   NULL,   NULL,   '2014-01-03 16:23:42',  NULL);

DROP TABLE IF EXISTS `user_group_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `user_group_user` AS (select `g`.`id` AS `id`,`g`.`group_id` AS `group_id`,`g`.`user_id` AS `user_id`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color`,`u`.`deleted_date` AS `deleted_date` from (`user_group` `g` join `user` `u` on((`g`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_comment_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_comment_user` AS (select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`,`c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comment` `c` join `user` `u` on((`c`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_detail`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_detail` AS (
  select
    `issue`.`id` AS `id`,
    `issue`.`status` AS `status`,
    `issue`.`type_id` AS `type_id`,
    `issue`.`name` AS `name`,
    `issue`.`description` AS `description`,
    `issue`.`parent_id` AS `parent_id`,
    `issue`.`author_id` AS `author_id`,
    `issue`.`owner_id` AS `owner_id`,
    `issue`.`priority` AS `priority`,
    `issue`.`hours_total` AS `hours_total`,
    `issue`.`hours_remaining` AS `hours_remaining`,
    `issue`.`created_date` AS `created_date`,
    `issue`.`closed_date` AS `closed_date`,
    `issue`.`deleted_date` AS `deleted_date`,
    `issue`.`due_date` AS `due_date`,
    `issue`.`due_date` IS NULL AS `has_due_date`,
    `issue`.`repeat_cycle` AS `repeat_cycle`,
    `issue`.`sprint_id` AS `sprint_id`,
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
    `owner`.`task_color` AS `owner_task_color`
  from (
    ((((`issue`
      left join `user` `author` on((`issue`.`author_id` = `author`.`id`)))
      left join `user` `owner` on((`issue`.`owner_id` = `owner`.`id`)))
      left join `issue_status` `status` on((`issue`.`status` = `status`.`id`)))
      left join `issue_priority` `priority` on((`issue`.`priority` = `priority`.`value`)))
      left join `issue_type` `type` on((`issue`.`type_id` = `type`.`id`)))
);

DROP TABLE IF EXISTS `issue_update_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_update_user` AS (select `i`.`id` AS `id`,`i`.`issue_id` AS `issue_id`,`i`.`user_id` AS `user_id`,`i`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`name` AS `user_name`,`u`.`email` AS `user_email` from (`issue_update` `i` join `user` `u` on((`i`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `issue_watcher_user`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `issue_watcher_user` AS (select `w`.`id` AS `watcher_id`,`w`.`issue_id` AS `issue_id`,`u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`u`.`name` AS `name`,`u`.`password` AS `password`,`u`.`role` AS `role`,`u`.`task_color` AS `task_color`,`u`.`created_date` AS `created_date`,`u`.`deleted_date` AS `deleted_date` from (`issue_watcher` `w` join `user` `u` on((`w`.`user_id` = `u`.`id`))));

DROP TABLE IF EXISTS `attribute`;

CREATE TABLE `attribute` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `type` enum('text','numeric','datetime','bool','list') NOT NULL DEFAULT 'text',
  `default` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `attribute_issue_type`;

CREATE TABLE `attribute_issue_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` int(10) unsigned NOT NULL,
  `issue_type_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_type` (`issue_type_id`),
  KEY `attribute_issue_type_attribute_id` (`attribute_id`),
  CONSTRAINT `attribute_issue_type_attribute_id` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `attribute_issue_type_issue_type_id` FOREIGN KEY (`issue_type_id`) REFERENCES `issue_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `attribute_value`;

CREATE TABLE `attribute_value` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` int(10) unsigned NOT NULL,
  `issue_id` int(10) unsigned NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `object` (`attribute_id`,`issue_id`),
  CONSTRAINT `attribute_value_attribute_id` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `attribute_value_detail`;

DROP VIEW IF EXISTS `attribute_value_detail`;
DROP TABLE IF EXISTS `attribute_value_detail`;

CREATE TABLE  `attribute_value_detail`(
 `id` int(10) unsigned ,
 `attribute_id` int(10) unsigned ,
 `issue_id` int(10) unsigned ,
 `value` text ,
 `name` varchar(64) ,
 `type` enum('text','numeric','datetime','bool','list') ,
 `default` text
)*/;

DROP TABLE IF EXISTS `attribute_value_detail`;
DROP VIEW IF EXISTS `attribute_value_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `attribute_value_detail` AS (select `v`.`id` AS `id`,`v`.`attribute_id` AS `attribute_id`,`v`.`issue_id` AS `issue_id`,`v`.`value` AS `value`,`a`.`name` AS `name`,`a`.`type` AS `type`,`a`.`default` AS `default` from (`attribute_value` `v` join `attribute` `a` on((`v`.`attribute_id` = `a`.`id`))));
