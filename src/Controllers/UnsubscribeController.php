<?php

namespace QuietRent\Controllers;

use QuietRent\Core\Env;

class UnsubscribeController
{
    private const UNSUB_FILE = BASE_PATH . '/tools/unsubscribed.txt';

    public function handle(array $_params): void
    {
        $email = trim($_GET['email'] ?? '');
        $token = trim($_GET['token'] ?? '');
        $secret = Env::get('UNSUBSCRIBE_SECRET', '');

        $valid = $email !== ''
            && $secret !== ''
            && hash_equals(hash_hmac('sha256', $email, $secret), $token);

        if (!$valid) {
            http_response_code(400);
            $this->render('Invalid or expired unsubscribe link.', false);
            return;
        }

        // Append to blocklist if not already there
        $existing = file_exists(self::UNSUB_FILE)
            ? array_map('trim', file(self::UNSUB_FILE))
            : [];

        if (!in_array(strtolower($email), array_map('strtolower', $existing), true)) {
            file_put_contents(self::UNSUB_FILE, strtolower($email) . "\n", FILE_APPEND | LOCK_EX);
        }

        $this->render("You've been unsubscribed. You won't hear from us again.", true);
    }

    private function render(string $message, bool $success): void
    {
        $color = $success ? '#22c55e' : '#ef4444';
        $title = $success ? 'Unsubscribed' : 'Invalid link';
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} - QuietNotify</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center;
           justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; }
    .card { background: #fff; border-radius: 12px; padding: 2.5rem 3rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; max-width: 420px; }
    h1 { font-size: 1.4rem; color: #111; margin: 0 0 .75rem; }
    p { color: #555; margin: 0; line-height: 1.6; }
    .dot { width: 48px; height: 48px; border-radius: 50%; background: {$color};
           display: flex; align-items: center; justify-content: center;
           margin: 0 auto 1.25rem; font-size: 1.5rem; }
  </style>
</head>
<body>
  <div class="card">
    <div class="dot">{$this->icon($success)}</div>
    <h1>{$title}</h1>
    <p>{$message}</p>
  </div>
</body>
</html>
HTML;
    }

    private function icon(bool $success): string
    {
        return $success ? '&#10003;' : '&#10007;';
    }
}
