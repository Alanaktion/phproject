# Add options column to user
ALTER TABLE `user` ADD `options` BLOB NULL AFTER `api_key`;

# Update Version
UPDATE `config` SET `value` = '15.10.07' WHERE `attribute` = 'version';
