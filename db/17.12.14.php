<?php
$f3 = \Base::instance();
$db = $f3->get("db.instance");

// Create new backlog structure
$setup = [];
$setup[] = "RENAME TABLE `issue_backlog` TO `issue_backlog_migration`";
$setup[] = "ALTER TABLE `issue_backlog_migration` DROP FOREIGN KEY `issue_backlog_sprint_id`";
$setup[] = "CREATE TABLE `issue_backlog_new`(
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sprint_id` int(10) UNSIGNED,
    `issue_id` int(10) UNSIGNED NOT NULL,
    `index` int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sprint_index` (`sprint_id`, `index`),
    CONSTRAINT `issue_backlog_sprint_id` FOREIGN KEY (`sprint_id`) REFERENCES `sprint`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `issue_backlog_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issue`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB";
$db->exec($setup);

// Migrate backlog items to new structure
$backlog = $db->exec("SELECT * FROM `issue_backlog_migration`");
foreach ($backlog as $row) {
    $issueIds = json_decode($row['issues']) ? : [];
    $issueIds = array_unique($issueIds);
    foreach ($issueIds as $index=>$id) {
        $db->exec(
            "INSERT INTO `issue_backlog_new` (`sprint_id`, `issue_id`, `index`) VALUES (?, ?, ?)",
            [1 => $row['sprint_id'], 2 => $id, 3 => $index]
        );
    }
}

// Finish up
$db->exec("RENAME TABLE `issue_backlog_new` TO `issue_backlog`");
$db->exec("DROP TABLE `issue_backlog_migration`");
$db->exec("UPDATE `config` SET `value` = '17.12.14' WHERE `attribute` = 'version'");
