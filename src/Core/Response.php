<?php

namespace QuietRent\Core;

class Response
{
    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function redirect(string $url): never
    {
        header("Location: $url");
        exit;
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        echo $message ?: $code;
        exit;
    }
}
