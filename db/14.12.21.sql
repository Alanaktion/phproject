# This database update occured after commit 58697b3b04

# Add language field to users
ALTER TABLE `user` ADD COLUMN `language` VARCHAR(5) NULL AFTER `theme`;

# Update Version
UPDATE `config` SET `value` = '14.12.21' WHERE `attribute` = 'version';
