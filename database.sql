/*
SQLyog Community v11.23 (64 bit)
MySQL - 5.5.32 : Database - freeproject
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`openproject` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `openproject`;

/*Table structure for table `categories` */

DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Data for the table `categories` */

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
  `description` varchar(64) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `due_date` date DEFAULT NULL,
  `repeat_cycle` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

/*Data for the table `issues` */

insert  into `issues`(`id`,`status`,`type_id`,`name`,`description`,`parent_id`,`creator_id`,`owner_id`,`created_date`,`due_date`,`repeat_cycle`) values (1,1,1,'This is a test task','This is a task.',NULL,1,1,'2013-10-18 22:00:00','2013-10-21','none'),(2,1,1,'Finish the task and project pages','This is another test task, this time with a much longer descript',NULL,1,1,'2013-10-19 05:09:36','2013-10-30','none'),(3,1,1,'No due date task','This task doesn\'t have a due date.',NULL,1,1,'2013-10-19 05:09:38',NULL,'none'),(4,1,1,'Due date task','This task does have a due date, and it\'s in the past!',NULL,1,1,'2013-10-19 05:09:40','2013-10-15','none');

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`email`,`name`,`password`,`role`) values (1,'alan','alan@iconic.co','Alan Hardman','$2y$13$H4JVZ7VP.Rguh9n8ROF5ueGSs6iSpAm9SRSr5nVCyCs260fTFUA5e','admin');

/*Table structure for table `watchers` */

DROP TABLE IF EXISTS `watchers`;

CREATE TABLE `watchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Data for the table `watchers` */

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
