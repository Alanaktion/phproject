-- Demo content should be imported immediately after the database.sql file

INSERT INTO `user` (`username`, `email`, `name`, `password`, `salt`, `role`, `rank`, `created_date`) VALUES
	('demo', 'demo@demo', 'Demo User', NULL, NULL, 'user', 2, CURRENT_TIMESTAMP),
	(NULL, NULL, 'Demo Group', NULL, NULL, 'group', NULL, CURRENT_TIMESTAMP);

INSERT INTO `user_group`(`user_id`,`group_id`,`manager`) VALUES (1,3,0), (2,3,0);

INSERT INTO `sprint` (`id`, `name`, `start_date`, `end_date`) VALUES
	(1, 'First Sprint', DATE(DATE(), '1 day'), DATE(DATE(), '14 days'));

INSERT INTO `issue` (`status`, `type_id`, `name`, `description`, `parent_id`, `author_id`, `owner_id`, `hours_total`, `hours_remaining`, `created_date`, `sprint_id`) VALUES
	(1, 2, 'A Big Project', 'This is a project.  Projects group tasks and bugs, and can go into sprints.', NULL, 2, 2, NULL, NULL, CURRENT_TIMESTAMP, 1),
	(1, 1, 'A Simple Task', 'This is a sample task.', 1, 1, 2, 2, 2, CURRENT_TIMESTAMP, NULL);
