/* Demo content should be imported immediately after the database.sql file */

SET NAMES utf8mb4;

INSERT INTO `user` (`username`, `email`, `name`, `password`, `salt`, `role`,`created_date`) VALUES
	('demo', 'demo@demo', 'Demo User', NULL, NULL, 'user', NOW()),
	(NULL, NULL, 'Demo Group', NULL, NULL, 'group', NOW());

INSERT INTO `user_group`(`user_id`,`group_id`,`manager`) VALUES (1,3,0), (2,3,0);

INSERT INTO `sprint` (`id`, `name`, `start_date`, `end_date`) VALUES
	(1, 'First Sprint', DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 2 DAY), INTERVAL 2 WEEK));

INSERT INTO `issue` (`status`, `type_id`, `name`, `description`, `parent_id`, `author_id`, `owner_id`, `hours_total`, `hours_remaining`, `created_date`, `sprint_id`) VALUES
	(1, 2, 'A Big Project', 'This is a project.  Projects group tasks and bugs, and can go into sprints.', NULL, 2, 2, NULL, NULL, NOW(), 1),
	(1, 1, 'A Simple Task', 'This is a sample task.', 1, 1, 2, 2, 2, NOW(), NULL);
