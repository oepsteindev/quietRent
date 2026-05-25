<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, DB, Response};

class AppointmentReminderRuleController
{
    public function index(array $params): void
    {
        Auth::require();
        $rules = DB::fetchAll(
            'SELECT * FROM appointment_reminder_rules WHERE account_id = ? ORDER BY stage',
            [Auth::accountId()]
        );
        Response::json($rules);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $id        = (int) $params['id'];

        $rule = DB::fetchOne(
            'SELECT * FROM appointment_reminder_rules WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );
        if (!$rule) {
            Response::json(['error' => 'Not found'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        DB::execute(
            'UPDATE appointment_reminder_rules SET subject=?, body=?, is_active=? WHERE id=? AND account_id=?',
            [
                $data['subject']   ?? $rule['subject'],
                $data['body']      ?? $rule['body'],
                (int) ($data['is_active'] ?? $rule['is_active']),
                $id,
                $accountId,
            ]
        );

        Response::json(['ok' => true]);
    }
}
