ALTER TABLE `issue` CHANGE `repeat_cycle` `repeat_cycle` VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
UPDATE `config` SET `value` = '17.09.20' WHERE `attribute` = 'version';
