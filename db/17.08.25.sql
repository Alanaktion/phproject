-- Remove unused tables
SET foreign_key_checks = 0;
DROP TABLE IF EXISTS attribute;
DROP TABLE IF EXISTS attribute_issue_type;
DROP TABLE IF EXISTS attribute_value;
DROP VIEW IF EXISTS `attribute_value_detail`;
SET foreign_key_checks = 1;

-- Add additional foreign keys
ALTER TABLE `issue_priority` ADD UNIQUE INDEX `priority` (`value`);
ALTER TABLE `issue`
	ADD CONSTRAINT `issue_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	ADD CONSTRAINT `issue_author_id` FOREIGN KEY (`author_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;

-- Update version
UPDATE `config` SET `value` = '17.08.25' WHERE `attribute` = 'version';
