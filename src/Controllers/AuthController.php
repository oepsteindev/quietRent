<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Env, Response};
use QuietRent\Models\{Account, User};

class AuthController
{
    public function showLogin(array $params): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        Auth::csrf();
        require __DIR__ . '/../../public/shell.php';
    }

    public function login(array $params): void
    {
        Auth::verifyCsrf();

        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $user = User::findByEmail($email);
        if (!$user || !User::verifyPassword($user, $password)) {
            Response::json(['error' => 'Invalid email or password.'], 401);
        }

        Auth::login($user);
        Response::json(['ok' => true]);
    }

    public function showRegister(array $params): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        Auth::csrf();
        require __DIR__ . '/../../public/shell.php';
    }

    public function register(array $params): void
    {
        Auth::verifyCsrf();

        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?? [];
        $name     = trim($data['name'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$name || !$email || !$password || strlen($password) < 8) {
            Response::json(['error' => 'All fields required; password must be at least 8 characters.'], 422);
        }

        if (User::findByEmail($email)) {
            Response::json(['error' => 'An account with that email already exists.'], 409);
        }

        $productType = in_array($data['product_type'] ?? '', ['landlords','dentists','agents','hairdressers'], true)
            ? $data['product_type']
            : 'landlords';

        $accountId = Account::create($name . "'s Account", $productType);
        $userId    = User::create($accountId, $name, $email, $password);
        Account::seedReminderRules($accountId);
        if ($productType === 'hairdressers') {
            Account::seedAppointmentReminderRules($accountId);
        }

        $user = User::find($userId);
        Auth::login($user);
        Response::json(['ok' => true]);
    }

    public function logout(array $params): void
    {
        Auth::logout();
        Response::json(['ok' => true]);
    }

    public function showForgot(array $params): void
    {
        require __DIR__ . '/../../public/shell.php';
    }

    public function forgot(array $params): void
    {
        Auth::verifyCsrf();

        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($data['email'] ?? '');
        $user  = User::findByEmail($email);

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            User::setResetToken($user['id'], $token, $expires);

            $resetUrl = rtrim(Env::get('APP_URL', ''), '/') . "/reset-password?token=$token";
            \QuietRent\Services\Mailer::send(
                $email,
                'Reset your Quiet Rent password',
                "Click to reset your password:\n\n$resetUrl\n\nThis link expires in 1 hour."
            );
        }

        Response::json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    public function showReset(array $params): void
    {
        require __DIR__ . '/../../public/shell.php';
    }

    public function reset(array $params): void
    {
        Auth::verifyCsrf();

        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?? [];
        $token    = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        $user = User::findByResetToken($token);
        if (!$user || strlen($password) < 8) {
            Response::json(['error' => 'Invalid or expired token.'], 422);
        }

        User::updatePassword($user['id'], $password);
        Response::json(['ok' => true]);
    }
}
