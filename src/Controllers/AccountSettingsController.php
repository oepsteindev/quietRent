<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};

class AccountSettingsController
{
    public function show(array $params): void
    {
        Auth::require();
        $row = DB::fetchOne(
            'SELECT payment_link, contact_phone, disclaimer FROM accounts WHERE id = ?',
            [Auth::accountId()]
        );
        Response::json($row ?: ['payment_link' => null, 'contact_phone' => null, 'disclaimer' => null]);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $binds  = [];

        if (array_key_exists('payment_link', $data)) {
            $fields[] = 'payment_link = ?';
            $binds[]  = trim($data['payment_link']) ?: null;
        }
        if (array_key_exists('contact_phone', $data)) {
            $fields[] = 'contact_phone = ?';
            $binds[]  = trim($data['contact_phone']) ?: null;
        }
        if (array_key_exists('disclaimer', $data)) {
            $fields[] = 'disclaimer = ?';
            $binds[]  = trim($data['disclaimer']) ?: null;
        }

        if ($fields) {
            $binds[] = Auth::accountId();
            DB::execute('UPDATE accounts SET ' . implode(', ', $fields) . ' WHERE id = ?', $binds);
        }

        Response::json(['ok' => true]);
    }
}
