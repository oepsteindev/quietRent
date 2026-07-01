<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};

class AdminController
{
    public function accounts(array $params): void
    {
        Auth::requireAdmin();

        $accounts = DB::fetchAll(
            'SELECT a.id, a.name, a.product_type, a.plan, a.subscription_status,
                    a.trial_ends_at, a.subscription_ends_at, a.created_at,
                    COALESCE(u1.email, u2.email) AS owner_email
             FROM accounts a
             LEFT JOIN users u1 ON u1.account_id = a.id
             LEFT JOIN user_accounts ua ON ua.account_id = a.id AND ua.role = "owner"
             LEFT JOIN users u2 ON u2.id = ua.user_id
             ORDER BY a.created_at DESC',
            []
        );

        Response::json($accounts);
    }

    public function updateAccount(array $params): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            Response::json(['error' => 'Missing id']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed_plans    = ['trial', 'starter', 'pro'];
        $allowed_statuses = ['trialing', 'active', 'past_due', 'canceled'];

        $sets   = [];
        $values = [];

        if (isset($data['plan']) && in_array($data['plan'], $allowed_plans, true)) {
            $sets[]   = 'plan = ?';
            $values[] = $data['plan'];
        }

        if (isset($data['subscription_status']) && in_array($data['subscription_status'], $allowed_statuses, true)) {
            $sets[]   = 'subscription_status = ?';
            $values[] = $data['subscription_status'];
        }

        if (array_key_exists('trial_ends_at', $data)) {
            $sets[]   = 'trial_ends_at = ?';
            $values[] = $data['trial_ends_at'] ? date('Y-m-d H:i:s', strtotime($data['trial_ends_at'])) : null;
        }

        if (!$sets) {
            http_response_code(400);
            Response::json(['error' => 'Nothing to update']);
            return;
        }

        $values[] = $id;
        DB::execute('UPDATE accounts SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);

        Response::json(['ok' => true]);
    }
}
