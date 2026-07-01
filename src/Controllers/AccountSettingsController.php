<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB, Env};
use QuietRent\Models\User;
use Stripe\StripeClient;

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

    public function deleteAccount(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = $data['password'] ?? '';

        $user = User::find(Auth::userId());
        if (!$user || !User::verifyPassword($user, $password)) {
            http_response_code(403);
            Response::json(['error' => 'Incorrect password.']);
            return;
        }

        // Collect all account IDs for this user
        $primary = (int)$user['account_id'];
        $others  = DB::fetchAll(
            'SELECT account_id FROM user_accounts WHERE user_id = ?',
            [Auth::userId()]
        );
        $accountIds = array_unique(array_merge(
            [$primary],
            array_column($others, 'account_id')
        ));

        // Cancel any active Stripe subscriptions
        $secretKey = Env::get('STRIPE_SECRET_KEY');
        if ($secretKey) {
            $stripe = new StripeClient(['api_key' => $secretKey]);
            $subs = DB::fetchAll(
                'SELECT stripe_subscription_id FROM accounts
                 WHERE id IN (' . implode(',', array_fill(0, count($accountIds), '?')) . ')
                   AND stripe_subscription_id IS NOT NULL',
                $accountIds
            );
            foreach ($subs as $sub) {
                try {
                    $stripe->subscriptions->cancel($sub['stripe_subscription_id']);
                } catch (\Exception $e) {
                    error_log('Stripe cancel on delete failed: ' . $e->getMessage());
                }
            }
        }

        // Delete accounts (CASCADE removes all related data)
        DB::execute(
            'DELETE FROM accounts WHERE id IN (' . implode(',', array_fill(0, count($accountIds), '?')) . ')',
            $accountIds
        );

        // Delete user (CASCADE removes user_accounts rows)
        DB::execute('DELETE FROM users WHERE id = ?', [Auth::userId()]);

        Auth::logout();
        Response::json(['ok' => true]);
    }
}
