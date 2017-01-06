# Add reset_token column to user table
ALTER TABLE `user`
	ADD COLUMN `reset_token` CHAR(96) NULL AFTER `salt`;

# Add default config entry for reset TTL
INSERT INTO `config` (`attribute`,`value`) VALUES ('security.reset_ttl', '86400');

# Update version
UPDATE `config` SET `value` = '16.12.29' WHERE `attribute` = 'version';
