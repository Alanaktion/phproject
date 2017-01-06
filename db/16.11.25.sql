# Merge group- and type-based backlog into single per-sprint lists
SET SESSION group_concat_max_len = 8192;

CREATE TEMPORARY TABLE issue_backlog_converting
SELECT sprint_id, REPLACE(GROUP_CONCAT(issues), '],[', ',')
FROM issue_backlog
GROUP BY sprint_id;

TRUNCATE issue_backlog;

ALTER TABLE issue_backlog
  DROP COLUMN user_id,
  DROP COLUMN type_id,
  DROP INDEX issue_backlog_user_id,
  DROP INDEX issue_backlog_type_id,
  DROP FOREIGN KEY issue_backlog_type_id,
  DROP FOREIGN KEY issue_backlog_user_id;

INSERT INTO issue_backlog (sprint_id, issues)
SELECT * FROM issue_backlog_converting;

DROP TEMPORARY TABLE issue_backlog_converting;

UPDATE `config` SET `value` = '16.11.25' WHERE `attribute` = 'version';
