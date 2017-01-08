# Reset user theme selection for removed themes
UPDATE user SET theme = NULL WHERE
	theme = 'css/bootstrap-amelia.min.css'
	OR theme = 'css/bootstrap-cosmo.min.css'
	OR theme = 'css/bootstrap-cupid.min.css'
	OR theme = 'css/bootstrap-google.css'
	OR theme = 'css/bootstrap-journal.min.css'
	OR theme = 'css/bootstrap-lumen.min.css'
	OR theme = 'css/bootstrap-paper.min.css'
	OR theme = 'css/bootstrap-readable.min.css'
	OR theme = 'css/bootstrap-shamrock.min.css'
	OR theme = 'css/bootstrap-simplex.min.css'
	OR theme = 'css/bootstrap-superhero.min.css'
	OR theme = 'css/bootstrap-united.min.css'
	OR theme = 'css/bootstrap-yeti.min.css';

UPDATE `config` SET `value` = '16.11.29.1' WHERE `attribute` = 'version';
