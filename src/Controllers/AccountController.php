<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};
use QuietRent\Models\Account;

class AccountController
{
    /** GET /api/accounts — list all accounts the current user belongs to */
    public function index(array $params): void
    {
        Auth::require();

        $accounts = DB::fetchAll(
            'SELECT a.id, a.name, a.product_type
             FROM accounts a
             JOIN user_accounts ua ON ua.account_id = a.id
             WHERE ua.user_id = ?
             ORDER BY a.name',
            [Auth::userId()]
        );

        $currentId = Auth::accountId();
        foreach ($accounts as &$a) {
            $a['is_current'] = ((int)$a['id'] === $currentId);
        }

        Response::json($accounts);
    }

    /** POST /api/accounts — create a new business and switch to it */
    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $name        = trim($data['name'] ?? '');
        $productType = in_array($data['product_type'] ?? '', ['landlords','dentists','agents','hairdressers','tradesmen'], true)
            ? $data['product_type']
            : 'landlords';

        if (!$name) {
            Response::json(['error' => 'Business name is required'], 422);
        }

        $accountId = Account::create($name, $productType);
        Account::seedReminderRules($accountId);
        if ($productType === 'hairdressers') {
            Account::seedAppointmentReminderRules($accountId);
        }
        if ($productType === 'tradesmen') {
            Account::seedJobReminderRules($accountId);
        }

        DB::execute(
            'INSERT IGNORE INTO user_accounts (user_id, account_id, role) VALUES (?, ?, ?)',
            [Auth::userId(), $accountId, 'owner']
        );

        Auth::switchAccount($accountId);

        Response::json(['id' => $accountId, 'product_type' => $productType], 201);
    }

    /**
     * POST /api/switch-to-vertical — switch session to the user's account for the given vertical.
     * Auto-creates the account if the user doesn't have one for that vertical yet.
     * Used by the admin ?vertical= testing flow so each vertical maps to its own isolated account.
     */
    public function switchToVertical(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $vertical = $data['vertical'] ?? '';

        $allowed = ['landlords','dentists','agents','hairdressers','tradesmen'];
        if (!in_array($vertical, $allowed, true)) {
            Response::json(['error' => 'Invalid vertical'], 422);
        }

        // Already on the right account?
        $current = Account::find(Auth::accountId());
        if ($current && $current['product_type'] === $vertical) {
            Response::json(['ok' => true, 'product_type' => $vertical]);
            return;
        }

        // Find user's existing account for this vertical
        $account = DB::fetchOne(
            'SELECT a.id FROM accounts a
             JOIN user_accounts ua ON ua.account_id = a.id
             WHERE ua.user_id = ? AND a.product_type = ?
             ORDER BY a.created_at ASC LIMIT 1',
            [Auth::userId(), $vertical]
        );

        if ($account) {
            Auth::switchAccount((int)$account['id']);
            Response::json(['ok' => true, 'product_type' => $vertical]);
            return;
        }

        // Auto-create a new account for this vertical
        $userName  = $_SESSION['user_name'] ?? 'My';
        $label     = ucfirst($vertical);
        $accountId = Account::create("{$userName}'s {$label}", $vertical);
        Account::seedReminderRules($accountId);
        if ($vertical === 'hairdressers') {
            Account::seedAppointmentReminderRules($accountId);
        }
        if ($vertical === 'tradesmen') {
            Account::seedJobReminderRules($accountId);
        }
        DB::execute(
            'INSERT IGNORE INTO user_accounts (user_id, account_id, role) VALUES (?, ?, ?)',
            [Auth::userId(), $accountId, 'owner']
        );
        Auth::switchAccount($accountId);

        Response::json(['ok' => true, 'product_type' => $vertical, 'created' => true]);
    }

    /** POST /api/switch-account — switch the active account in the session */
    public function switchAccount(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $accountId = (int)($data['account_id'] ?? 0);

        $membership = DB::fetchOne(
            'SELECT a.id, a.product_type
             FROM accounts a
             JOIN user_accounts ua ON ua.account_id = a.id
             WHERE ua.user_id = ? AND a.id = ?',
            [Auth::userId(), $accountId]
        );

        if (!$membership) {
            Response::json(['error' => 'Not authorized'], 403);
        }

        Auth::switchAccount($accountId);

        Response::json(['ok' => true, 'product_type' => $membership['product_type']]);
    }
}
