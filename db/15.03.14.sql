ALTER TABLE `issue_update` ADD COLUMN `notify` TINYINT(1) UNSIGNED DEFAULT 0  NOT NULL AFTER `comment_id`;
UPDATE `config` SET `value` = '15.03.14' WHERE `attribute` = 'version';
