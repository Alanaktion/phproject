# Update issue_backlog table structure
ALTER TABLE issue_backlog
	ADD COLUMN type_id INT(10) UNSIGNED NULL AFTER user_id;

UPDATE issue_backlog b
JOIN config c ON c.attribute = 'issue_type.project'
SET b.type_id = c.value;

ALTER TABLE issue_backlog
	CHANGE type_id type_id INT(10) UNSIGNED NOT NULL,
	ADD CONSTRAINT issue_backlog_type_id FOREIGN KEY (type_id)
		REFERENCES issue_type(id) ON UPDATE CASCADE ON DELETE CASCADE;

# Update version
UPDATE `config` SET `value` = '16.09.12' WHERE `attribute` = 'version';
