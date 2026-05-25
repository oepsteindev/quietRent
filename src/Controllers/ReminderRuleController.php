<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};

class ReminderRuleController
{
    public function index(array $params): void
    {
        Auth::require();
        $rules = DB::fetchAll(
            'SELECT * FROM reminder_rules WHERE account_id = ? ORDER BY day_offset',
            [Auth::accountId()]
        );
        Response::json($rules);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id   = (int) $params['id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rule = DB::fetchOne(
            'SELECT id FROM reminder_rules WHERE id = ? AND account_id = ?',
            [$id, Auth::accountId()]
        );
        if (!$rule) {
            Response::abort(404, 'Not found');
        }

        DB::execute(
            'UPDATE reminder_rules SET subject=?, body=?, is_active=? WHERE id=? AND account_id=?',
            [
                $body['subject'] ?? '',
                $body['body'] ?? '',
                (int) ($body['is_active'] ?? 1),
                $id,
                Auth::accountId(),
            ]
        );

        Response::json(['ok' => true]);
    }
}
