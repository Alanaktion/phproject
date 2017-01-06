# Update issues table to allow null repeat_cycle
ALTER TABLE `issue` CHANGE `repeat_cycle`
	`repeat_cycle` VARCHAR(10) CHARSET utf8 COLLATE utf8_general_ci NULL;

# Change existing repeat_cycle 'none' values to NULL
UPDATE issue SET repeat_cycle = NULL WHERE repeat_cycle IN('none', '');

# Update version
UPDATE `config` SET `value` = '16.06.28' WHERE `attribute` = 'version';
