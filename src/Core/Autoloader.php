<?php

namespace QuietRent\Core;

class Autoloader
{
    public static function register(string $basePath): void
    {
        spl_autoload_register(function (string $class) use ($basePath): void {
            // QuietRent\Core\DB -> /src/Core/DB.php
            $prefix = 'QuietRent\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file     = $basePath . '/src/' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
