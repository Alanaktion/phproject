<?php
$less = <<<EOT
@import "../../../bower_components/bootstrap/less/bootstrap.less";
@import "%DIR%/variables.less";
@import "%DIR%/bootswatch.less";
@import "../style.less";

EOT;

foreach(glob("../bootswatch/*") as $dir) {
	file_put_contents(basename($dir).".less", str_replace("%DIR%", $dir, $less));
}
