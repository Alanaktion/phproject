<?php

namespace Controller;

use Leafo\ScssPhp\Compiler;

class Style extends \Controller
{
    /**
     * SCSS compiler
     *
     * @param  \Base $fw
     * @param  array $params
     * @return void
     */
    public function index(\Base $fw, array $params)
    {
        $scss = new Compiler();
        $scss->setImportPaths('scss/');
        if (preg_match("/[^a-z]/", $params['file'])) {
            $fw->error(400);
            return;
        }
        if (is_file('../../scss/' . $params['file'] . '.scss')) {
            $fw->error(404);
            return;
        }
        header("Content-Type: text/css");
        echo $scss->compile('@import "' . $params['file'] . '.scss";');
    }
}
