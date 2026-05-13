# Expand password column for bcrypt hashes
ALTER TABLE `user`
	MODIFY COLUMN `password` VARCHAR(80) NULL;

# Update version
UPDATE `config` SET `value` = '26.05.13' WHERE `attribute` = 'version';
