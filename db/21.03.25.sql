-- Add default description to issue types
ALTER VIEW `user_group_user`
AS (select `g`.`id` AS `id`,`g`.`group_id` AS `group_id`,`g`.`user_id` AS `user_id`,`u`.`username` AS `user_username`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`u`.`role` AS `user_role`,`u`.`task_color` AS `user_task_color`,`u`.`deleted_date` AS `deleted_date`,`g`.`manager` AS `manager`,`g`.`mailbox` AS `mailbox` from (`user_group` `g` join `user` `u` on((`g`.`user_id` = `u`.`id`))));

UPDATE `config` SET `value` = '21.03.25' WHERE `attribute` = 'version';