SET foreign_key_checks = 0;

-- Remove unused attribute tables
DROP TABLE IF EXISTS attribute;
DROP TABLE IF EXISTS attribute_issue_type;
DROP TABLE IF EXISTS attribute_value;
DROP VIEW IF EXISTS `attribute_value_detail`;

-- Make ID column types consistent between tables
ALTER TABLE `issue`
	CHANGE `type_id` `type_id` INT(10) UNSIGNED DEFAULT 1 NOT NULL,
	CHANGE `parent_id` `parent_id` INT(10) UNSIGNED NULL,
	CHANGE `author_id` `author_id` INT(10) UNSIGNED NOT NULL,
	CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL,
	CHANGE `priority` `priority` INT(10) DEFAULT 0 NOT NULL,
	CHANGE `sprint_id` `sprint_id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `issue_dependency`
	CHANGE `dependency_id` `dependency_id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `issue_priority`
	CHANGE `value` `value` INT(10) NOT NULL,
	ADD UNIQUE INDEX `priority` (`value`);

SET foreign_key_checks = 1;

-- Add additional foreign keys
ALTER TABLE `issue`
	ADD CONSTRAINT `issue_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	ADD CONSTRAINT `issue_priority` FOREIGN KEY (`priority`) REFERENCES `issue_priority`(`value`) ON UPDATE CASCADE ON DELETE RESTRICT,
	ADD CONSTRAINT `issue_author_id` FOREIGN KEY (`author_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

-- Update version
UPDATE `config` SET `value` = '17.08.25' WHERE `attribute` = 'version';
