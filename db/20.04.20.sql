INSERT INTO `config` (`attribute`,`value`) VALUES ('security.file_blacklist', '/\.(ph(p([3457s]|\-s)?|t|tml)|aspx?|shtml|exe|dll)$/i');
UPDATE `config` SET `value` = '20.04.20' WHERE `attribute` = 'version';
