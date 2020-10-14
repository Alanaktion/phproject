DROP TABLE IF EXISTS `session`;
CREATE TABLE `session`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`token` VARBINARY(64) NOT NULL,
	`user_id` INT UNSIGNED NOT NULL,
	`created` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `session_token` (`token`),
	KEY `session_user_id` (`user_id`),
	CONSTRAINT `session_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `config` SET `value` = '15.03.20' WHERE `attribute` = 'version';
