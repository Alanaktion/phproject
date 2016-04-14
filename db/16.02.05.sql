# Add sprint_id column to issue_backlog
ALTER TABLE `issue_backlog`
	ADD COLUMN `sprint_id` INT UNSIGNED NULL AFTER `user_id`,
	ADD CONSTRAINT `issue_backlog_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;

# Update version
UPDATE `config` SET `value` = '16.02.05' WHERE `attribute` = 'version';
