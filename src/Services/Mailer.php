<?php

namespace QuietRent\Services;

use QuietRent\Core\Env;

class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        if (self::isUnsubscribed($to)) {
            error_log("Mailer: skipping unsubscribed address: $to");
            return false;
        }
        return self::sendMail($to, $subject, $body);
    }

    public static function sendWithPdf(string $to, string $subject, string $body, string $pdfBytes, string $filename): bool
    {
        if (self::isUnsubscribed($to)) {
            error_log("Mailer: skipping unsubscribed address: $to");
            return false;
        }
        return self::sendMail($to, $subject, $body, $pdfBytes, $filename);
    }

    public static function unsubscribeUrl(string $email): string
    {
        $secret = Env::get('UNSUBSCRIBE_SECRET', '');
        $token  = hash_hmac('sha256', $email, $secret);
        $base   = 'https://landlords.getquietnotify.com';
        return $base . '/unsubscribe?email=' . urlencode($email) . '&token=' . $token;
    }

    private static function isUnsubscribed(string $email): bool
    {
        $file = BASE_PATH . '/tools/unsubscribed.txt';
        if (!file_exists($file)) {
            return false;
        }
        $list = array_map('trim', file($file));
        return in_array(strtolower($email), array_map('strtolower', $list), true);
    }

    private static function sendMail(string $to, string $subject, string $body, ?string $pdfBytes = null, ?string $filename = null): bool
    {
        $apiKey = Env::get('RESEND_API_KEY');

        if ($apiKey) {
            return self::sendViaResend($apiKey, $to, $subject, $body, $pdfBytes, $filename);
        }

        error_log("Mailer: RESEND_API_KEY not configured — email not sent to $to");
        return false;
    }

    private static function sendViaResend(string $apiKey, string $to, string $subject, string $body, ?string $pdfBytes, ?string $filename): bool
    {
        $from = Env::get('MAIL_FROM_NAME', 'QuietNotify') . ' <' . Env::get('MAIL_FROM', 'support@getquietnotify.com') . '>';

        $payload = [
            'from'    => $from,
            'to'      => [$to],
            'subject' => $subject,
            'text'    => $body,
        ];

        if ($pdfBytes !== null && $filename !== null) {
            $payload['attachments'] = [[
                'filename' => $filename,
                'content'  => base64_encode($pdfBytes),
            ]];
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            error_log("Mailer curl error: $curlErr");
            return false;
        }

        if ($status < 200 || $status >= 300) {
            error_log("Mailer Resend error (HTTP $status): $raw");
            return false;
        }

        error_log("Mailer: sent to $to via Resend");
        return true;
    }
}
