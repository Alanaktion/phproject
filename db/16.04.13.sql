# Add api_visible column to user table
ALTER TABLE `user`
	ADD COLUMN `api_visible` TINYINT(1) UNSIGNED DEFAULT 1 NOT NULL AFTER `api_key`;

# Update version
UPDATE `config` SET `value` = '16.04.13' WHERE `attribute` = 'version';
