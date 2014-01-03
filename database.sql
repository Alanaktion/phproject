/*
SQLyog Community v11.3 (64 bit)
MySQL - 5.5.16-log : Database - openproject
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*Table structure for table `categories` */

DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Data for the table `categories` */

/*Table structure for table `issue_comments` */

DROP TABLE IF EXISTS `issue_comments`;

CREATE TABLE `issue_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

/*Data for the table `issue_comments` */

insert  into `issue_comments`(`id`,`issue_id`,`user_id`,`text`,`created_date`) values (1,4,1,'Holy crap a comment.','2014-01-02 15:24:55'),(2,4,1,'Testy','2014-01-03 00:42:54'),(3,4,1,'Sweet :D','2014-01-03 00:43:00');

/*Table structure for table `issue_statuses` */

DROP TABLE IF EXISTS `issue_statuses`;

CREATE TABLE `issue_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

/*Data for the table `issue_statuses` */

insert  into `issue_statuses`(`id`,`name`,`closed`) values (1,'New',0),(2,'Active',0),(3,'Completed',1),(4,'On Hold',0);

/*Table structure for table `issue_types` */

DROP TABLE IF EXISTS `issue_types`;

CREATE TABLE `issue_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

/*Data for the table `issue_types` */

insert  into `issue_types`(`id`,`name`) values (1,'Task'),(2,'Project'),(3,'Bug');

/*Table structure for table `issues` */

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

/*Data for the table `issues` */

insert  into `issues`(`id`,`status`,`type_id`,`name`,`description`,`parent_id`,`author_id`,`owner_id`,`created_date`,`due_date`,`repeat_cycle`) values (1,1,1,'This is a test task','This is a task.',NULL,1,1,'2013-10-18 22:00:00','2013-10-21','none'),(2,1,1,'Finish the task and project pages','This is another test task, this time with a much longer description.',NULL,1,1,'2013-10-19 05:09:36','2013-10-30','none'),(3,1,1,'No due date task','This task doesn\'t have a due date.',NULL,2,1,'2013-10-19 05:09:38',NULL,'none'),(4,1,1,'Due date task','This task does have a due date, and it\'s in the past!',NULL,2,1,'2013-10-19 05:09:40','2013-10-15','none'),(5,1,1,'Test','Testing',0,0,1,'0000-00-00 00:00:00','1970-01-01','none'),(6,1,1,'Testing Other Assignee','Testy testy testy.',2,1,2,'2013-12-20 21:47:18','2013-12-23','none');

/*Table structure for table `task_comments` */

DROP TABLE IF EXISTS `task_comments`;

CREATE TABLE `task_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Data for the table `task_comments` */

/*Table structure for table `users` */

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`email`,`name`,`password`,`role`,`task_color`) values (1,'alan','ahardman@thrivelife.com','Alan Hardman','$2y$13$rTDxcLCsS/pZRIbLb6vW4.SP/qAn7y/QhWO8WfewsIyhrn9KMLJK2','admin','b5ed3f'),(2,'shelf','shelf@localhost','Shelf Testy','$2y$13$TDAyoRKvtNyRo08/Ova4YOfCFlXgm7/qKLuw2mW7EHHefcrlRze92','user','336699');

/*Table structure for table `watchers` */

DROP TABLE IF EXISTS `watchers`;

CREATE TABLE `watchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Data for the table `watchers` */

/*Table structure for table `issue_comments_user_data` */

DROP TABLE IF EXISTS `issue_comments_user_data`;

/*!50001 DROP VIEW IF EXISTS `issue_comments_user_data` */;
/*!50001 DROP TABLE IF EXISTS `issue_comments_user_data` */;

/*!50001 CREATE TABLE  `issue_comments_user_data`(
 `id` int(10) unsigned ,
 `issue_id` int(10) unsigned ,
 `user_id` int(10) unsigned ,
 `text` text ,
 `created_date` datetime ,
 `user_username` varchar(32) ,
 `user_email` varchar(64) ,
 `user_name` varchar(32) ,
 `user_role` enum('user','admin') ,
 `user_task_color` char(6) 
)*/;

/*Table structure for table `issues_user_data` */

DROP TABLE IF EXISTS `issues_user_data`;

/*!50001 DROP VIEW IF EXISTS `issues_user_data` */;
/*!50001 DROP TABLE IF EXISTS `issues_user_data` */;

/*!50001 CREATE TABLE  `issues_user_data`(
 `id` int(10) unsigned ,
 `status` int(11) ,
 `type_id` int(11) ,
 `name` varchar(64) ,
 `description` text ,
 `parent_id` int(11) ,
 `author_id` int(11) ,
 `owner_id` int(11) ,
 `created_date` datetime ,
 `due_date` date ,
 `repeat_cycle` enum('none','daily','weekly','monthly') ,
 `author_username` varchar(32) ,
 `author_name` varchar(32) ,
 `author_email` varchar(64) ,
 `owner_username` varchar(32) ,
 `owner_name` varchar(32) ,
 `owner_email` varchar(64) 
)*/;

/*View structure for view issue_comments_user_data */

/*!50001 DROP TABLE IF EXISTS `issue_comments_user_data` */;
/*!50001 DROP VIEW IF EXISTS `issue_comments_user_data` */;

/*!50001 CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `issue_comments_user_data` AS (select `c`.`id` AS `id`,`c`.`issue_id` AS `issue_id`,`c`.`user_id` AS `user_id`,`c`.`text` AS `text`,`c`.`created_date` AS `created_date`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color` from (`issue_comments` `c` join `users` `u` on((`c`.`user_id` = `u`.`id`)))) */;

/*View structure for view issues_user_data */

/*!50001 DROP TABLE IF EXISTS `issues_user_data` */;
/*!50001 DROP VIEW IF EXISTS `issues_user_data` */;

/*!50001 CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `issues_user_data` AS (select `issue`.`id` AS `id`,`issue`.`status` AS `status`,`issue`.`type_id` AS `type_id`,`issue`.`name` AS `name`,`issue`.`description` AS `description`,`issue`.`parent_id` AS `parent_id`,`issue`.`author_id` AS `author_id`,`issue`.`owner_id` AS `owner_id`,`issue`.`created_date` AS `created_date`,`issue`.`due_date` AS `due_date`,`issue`.`repeat_cycle` AS `repeat_cycle`,`author`.`username` AS `author_username`,`author`.`name` AS `author_name`,`author`.`email` AS `author_email`,`owner`.`username` AS `owner_username`,`owner`.`name` AS `owner_name`,`owner`.`email` AS `owner_email` from ((`issues` `issue` left join `users` `author` on((`issue`.`author_id` = `author`.`id`))) left join `users` `owner` on((`issue`.`owner_id` = `owner`.`id`)))) */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
