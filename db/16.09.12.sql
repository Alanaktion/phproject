# Update issue_backlog table structure
ALTER TABLE issue_backlog ADD COLUMN type_id VARCHAR(30) NULL AFTER user_id;

UPDATE issue_backlog b
JOIN config c ON c.attribute = 'issue_type.project'
SET b.type_id = c.value;

# Update version
UPDATE `config` SET `value` = '16.09.12' WHERE `attribute` = 'version';
