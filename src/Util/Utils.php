<?php
namespace Watermoss\Util;

class Utils {
    public static function readProperties(string $path): array {
        $out = [];
        if (!is_file($path)) return $out;
        foreach (file($path) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) $out[trim($parts[0])] = trim($parts[1]);
        }
        return $out;
    }

    public static function now(): string {
        return date('Y-m-d H:i:s');
    }
}
