# Update issue_type to support roles
ALTER TABLE `issue_type`
  ADD COLUMN `role` ENUM('task','project','bug') DEFAULT 'task' NOT NULL,
  ADD INDEX `issue_type_role` (`role`);

UPDATE issue_type
JOIN config ON config.value = issue_type.id AND config.attribute = 'issue_type.project'
SET issue_type.role = 'project';

UPDATE issue_type
JOIN config ON config.value = issue_type.id AND config.attribute = 'issue_type.bug'
SET issue_type.role = 'bug';

UPDATE `config` SET `value` = '16.11.23' WHERE `attribute` = 'version';
