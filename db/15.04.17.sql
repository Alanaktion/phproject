# Add Issue dependency
DROP TABLE IF EXISTS `issue_dependency`;
CREATE TABLE `issue_dependency` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `issue_id` int(10) unsigned NOT NULL,
	  `dependency_id` int(11) unsigned NOT NULL,
	  `dependency_type` char(2) COLLATE utf8_unicode_ci NOT NULL,
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `issue_id_dependency_id` (`issue_id`,`dependency_id`),
	  KEY `dependency_id` (`dependency_id`),
	  CONSTRAINT `issue_dependency_ibfk_2` FOREIGN KEY (`issue_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  CONSTRAINT `issue_dependency_ibfk_3` FOREIGN KEY (`dependency_id`) REFERENCES `issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Update Version
UPDATE `config` SET `value` = '15.04.17' WHERE `attribute` = 'version';
