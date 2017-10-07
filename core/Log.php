<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 09:57
 */

namespace inframe\core;


final class Log
{

    public static function getLogger(string $loggerName = 'default'): Log
    {

    }


    public static function save(): void
    {
    }

}

register_shutdown_function(function () {
    Log::save();
});