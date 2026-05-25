<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class User
{
    public static function findByEmail(string $email): array|false
    {
        return DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function find(int $id): array|false
    {
        return DB::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function create(int $accountId, string $name, string $email, string $password, string $role = 'landlord'): int
    {
        return DB::insert(
            'INSERT INTO users (account_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$accountId, $name, $email, password_hash($password, PASSWORD_BCRYPT), $role]
        );
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public static function setResetToken(int $id, string $token, string $expires): void
    {
        DB::execute(
            'UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?',
            [$token, $expires, $id]
        );
    }

    public static function findByResetToken(string $token): array|false
    {
        return DB::fetchOne(
            'SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()',
            [$token]
        );
    }

    public static function updatePassword(int $id, string $password): void
    {
        DB::execute(
            'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT), $id]
        );
    }
}
