<?php

namespace Helper;

class Template
{
    public static function csrfToken($args)
    {
        return '<input type="hidden" name="csrf-token" value="<?= $this->esc($csrf_token) ?>" />';
    }
}
