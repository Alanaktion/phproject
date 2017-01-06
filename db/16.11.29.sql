# New backlog uses only one row per sprint
ALTER TABLE issue_backlog
  DROP INDEX issue_backlog_sprint_id,
  ADD UNIQUE INDEX issue_backlog_sprint_id (sprint_id);

UPDATE `config` SET `value` = '16.11.29' WHERE `attribute` = 'version';
