# This database update occured after commit f6f4e8de
# This update may take a while to run on large databases!

# Clean potentially messy data that could break the upgrade process
UPDATE issue SET owner_id = NULL WHERE owner_id = 0;
UPDATE issue SET sprint_id = NULL WHERE sprint_id = 0;

# Adding foreign key constraints to issue metadata
ALTER TABLE `issue`
	CHANGE `status` `status` INT(10) UNSIGNED DEFAULT 1 NOT NULL;
ALTER TABLE `issue`
	ADD INDEX `status` (`status`);
ALTER TABLE `issue`
	ADD CONSTRAINT `issue_type_id` FOREIGN KEY (`type_id`) REFERENCES `issue_type`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
	ADD CONSTRAINT `issue_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	ADD CONSTRAINT `issue_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	ADD CONSTRAINT `issue_status` FOREIGN KEY (`status`) REFERENCES `issue_status`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

# Prevent deleting users with live comments
ALTER TABLE `issue_comment` DROP FOREIGN KEY `comment_user`;
ALTER TABLE `issue_comment` ADD CONSTRAINT `comment_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

# Update Version
UPDATE `config` SET `value` = '14.12.30' WHERE `attribute` = 'version';
