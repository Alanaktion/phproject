/*
Rank 0: guest - read-only access
Rank 1: client - read-only access + comments
Rank 2: user - current user permissions
Rank 3: manager - delete issues/comments
Rank 4: admin - current admin privileges, minus plugin config
Rank 5: superadmin - able to change config file values from web interface
*/

ALTER TABLE user ADD COLUMN rank tinyint(1) UNSIGNED DEFAULT 0 NOT NULL AFTER role;
UPDATE user SET rank = '2' WHERE role = 'user';
UPDATE user SET rank = '4' WHERE role = 'admin';
UPDATE config SET value = '15.02.07' WHERE attribute = 'version';
