-- Add default description to issue types
ALTER TABLE `issue_type`
ADD `default_description` text NULL AFTER `role`;

UPDATE `config` SET `value` = '21.03.18' WHERE `attribute` = 'version';
