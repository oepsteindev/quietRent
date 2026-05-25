<?php

namespace QuietRent\Services;

use QuietRent\Core\Env;

class SMS
{
    /**
     * Send an SMS via Textbelt.
     *
     * @param string $to      Phone number (E.164 or 10-digit US)
     * @param string $message Plain-text message body
     */
    public static function send(string $to, string $message): bool
    {
        if (self::isQuietHours()) {
            error_log("SMS not sent - quiet hours in effect: {$to}");
            return false;
        }

        $apiKey = Env::get('TEXTBELT_KEY');

        if (empty($apiKey)) {
            error_log('TEXTBELT_KEY not configured in .env');
            return false;
        }

        $to = self::normalizePhoneNumber($to);

        if (!$to) {
            error_log('SMS: invalid phone number format');
            return false;
        }

        $ch = curl_init('https://textbelt.com/text');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'phone'   => $to,
                'message' => $message,
                'key'     => $apiKey,
            ]),
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("SMS curl error: {$curlErr}");
            return false;
        }

        $resp = json_decode($raw, true);

        if (!($resp['success'] ?? false)) {
            error_log('SMS send failed: ' . ($resp['error'] ?? $raw));
            return false;
        }

        error_log('SMS sent via Textbelt. textId=' . ($resp['textId'] ?? 'n/a') . ' quotaRemaining=' . ($resp['quotaRemaining'] ?? '?'));
        return true;
    }

    private static function isQuietHours(): bool
    {
        $hour = (int) date('G');
        return $hour >= 22 || $hour < 6;
    }

    private static function normalizePhoneNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '+1' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        } elseif (str_starts_with($phone, '+')) {
            return $phone;
        }

        return null;
    }
}
