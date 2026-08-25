<?php

namespace App\Utils;

final class Config
{
    private static array $config = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset(self::$config[$file])) {
            $path = __DIR__ . '/../../config/' . $file . '.php';
            if (file_exists($path)) {
                self::$config[$file] = require $path;
            } else {
                self::$config[$file] = [];
            }
        }

        $value = self::$config[$file];
        for ($i = 1; $i < count($parts); $i++) {
            if (!is_array($value) || !isset($value[$parts[$i]])) {
                return $default;
            }
            $value = $value[$parts[$i]];
        }

        return $value;
    }
}
