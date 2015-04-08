DELETE FROM `issue_update_field`
WHERE `issue_update_id` NOT IN (
	SELECT `id` FROM `issue_update`
);

ALTER TABLE `issue_update_field`
ADD CONSTRAINT `issue_update_field_update` FOREIGN KEY (`issue_update_id`) REFERENCES `issue_update`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
ENGINE=INNODB;

UPDATE `config` SET `value` = '15.04.07' WHERE `attribute` = 'version';
