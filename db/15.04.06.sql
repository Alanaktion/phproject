ALTER TABLE `session`
	ADD COLUMN `ip` VARBINARY(39) NOT NULL AFTER `token`,
	DROP INDEX `session_token`, ADD UNIQUE INDEX `session_token` (`token`, `ip`);
TRUNCATE `session`;

UPDATE `config` SET `value` = '15.04.06' WHERE `attribute` = 'version';
