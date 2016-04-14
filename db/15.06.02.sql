# Update issue update detail view
ALTER VIEW `issue_update_detail` AS (
	select
		`i`.`id` AS `id`,
		`i`.`issue_id` AS `issue_id`,
		`i`.`user_id` AS `user_id`,
		`i`.`created_date` AS `created_date`,
		`u`.`username` AS `user_username`,
		`u`.`name` AS `user_name`,
		`u`.`email` AS `user_email`,
		`i`.`comment_id` AS `comment_id`,
		`c`.`text` AS `comment_text`,
		`i`.`notify` AS `notify`
	from `issue_update` `i`
		inner join `user` `u` on `i`.`user_id` = `u`.`id`
		left join `issue_comment` `c` on `i`.`comment_id` = `c`.`id`
);

# Update Version
UPDATE `config` SET `value` = '15.06.12' WHERE `attribute` = 'version';
