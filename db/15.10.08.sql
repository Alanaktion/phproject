# Add notification database table
CREATE TABLE `user_notification` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int(10) unsigned NOT NULL,
	`issue_id` int(10) unsigned NOT NULL,
	`comment_id` int(10) unsigned DEFAULT NULL,
	`update_id` int(10) unsigned DEFAULT NULL,
	`file_id` int(10) unsigned DEFAULT NULL,
	`action` varbinary(40) NOT NULL,
	`text` tinytext NOT NULL,
	`created_date` datetime NOT NULL,
	`read_date` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `user_notification_user_id` (`user_id`),
	KEY `user_notification_issue_id` (`issue_id`),
	KEY `user_notification_comment_id` (`comment_id`),
	KEY `user_notification_update_id` (`update_id`),
	KEY `user_notification_file_id` (`file_id`),
	KEY `user_notification_created_date` (`created_date`),
	KEY `user_notification_read_date` (`read_date`),
	CONSTRAINT `user_notification_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `user_notification_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `user_notification_comment_id` FOREIGN KEY (`comment_id`) REFERENCES `issue_comment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `user_notification_update_id` FOREIGN KEY (`update_id`) REFERENCES `issue_update` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `user_notification_file_id` FOREIGN KEY (`file_id`) REFERENCES `issue_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Update Version
UPDATE `config` SET `value` = '15.10.08' WHERE `attribute` = 'version';
