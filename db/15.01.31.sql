DROP TABLE IF EXISTS `issue_tag`;
CREATE TABLE `issue_tag`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tag` VARCHAR(60) NOT NULL,
	`issue_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `issue_tag_tag` (`tag`, `issue_id`),
	CONSTRAINT `issue_tag_issue` FOREIGN KEY (`issue_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB CHARSET=utf8;

UPDATE `config` SET `value` = '15.01.31' WHERE `attribute` = 'version';
