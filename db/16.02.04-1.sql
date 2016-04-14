# Add issue_backlog table
CREATE TABLE `issue_backlog`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`issues` BLOB NOT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `issue_backlog_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB CHARSET=utf8;

# Update version
UPDATE `config` SET `value` = '16.02.04-1' WHERE `attribute` = 'version';
