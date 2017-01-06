ALTER VIEW `issue_comment_detail` AS (
	SELECT
		`c`.`id` AS `id`,
		`c`.`issue_id` AS `issue_id`,
		`c`.`user_id` AS `user_id`,
		`c`.`text` AS `text`,
		`c`.`file_id` AS `file_id`,
		`c`.`created_date` AS `created_date`,
		`u`.`username` AS `user_username`,
		`u`.`email` AS `user_email`,
		`u`.`name` AS `user_name`,
		`u`.`role` AS `user_role`,
		`u`.`task_color` AS `user_task_color`,
		`f`.`filename` AS `file_filename`,
		`f`.`filesize` AS `file_filesize`,
		`f`.`content_type` AS `file_content_type`,
		`f`.`downloads` AS `file_downloads`,
		`f`.`created_date` AS `file_created_date`,
		`f`.`deleted_date` AS `file_deleted_date`,
		`i`.`deleted_date` AS `issue_deleted_date`
	FROM `issue_comment` `c`
	JOIN `user` `u` ON `c`.`user_id` = `u`.`id`
	LEFT JOIN `issue_file` `f` ON `c`.`file_id` = `f`.`id`
	JOIN `issue` `i` ON `i`.`id` = `c`.`issue_id`
);

UPDATE `config` SET `value` = '16.11.30' WHERE `attribute` = 'version';
