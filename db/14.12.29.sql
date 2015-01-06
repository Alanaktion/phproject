# This database update occured after commit a2a868d3

# Adding index to parent_id column
ALTER TABLE `issue` ADD  INDEX `parent_id` (`parent_id`);

# Update Version
UPDATE `config` SET `value` = '14.12.29' WHERE `attribute` = 'version';
