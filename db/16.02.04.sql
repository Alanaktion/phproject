# Add taskboard_sort column to issue_status
ALTER TABLE `issue_status`
	ADD COLUMN `taskboard_sort` INT UNSIGNED NULL AFTER `taskboard`;
UPDATE `issue_status` SET `taskboard_sort` = '1' WHERE `taskboard` > 0;

# Update version
UPDATE `config` SET `value` = '16.02.04' WHERE `attribute` = 'version';
