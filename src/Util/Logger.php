<?php
namespace Watermoss\Util;

class Logger {
    public static function info(string $msg): void {
        echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    }

    public static function error(string $msg): void {
        echo '[' . date('H:i:s') . '] ERROR: ' . $msg . PHP_EOL;
    }
}
