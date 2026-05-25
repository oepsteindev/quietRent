<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Tenant
{
    public static function allForAccount(int $accountId): array
    {
        return DB::fetchAll(
            'SELECT t.*, u.unit_label, p.name as property_name, p.id as property_id
             FROM tenants t
             JOIN units u ON u.id = t.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE t.account_id = ? AND t.is_active = 1
             ORDER BY p.name, u.unit_label, t.full_name',
            [$accountId]
        );
    }

    public static function allForUnit(int $unitId): array
    {
        return DB::fetchAll(
            'SELECT * FROM tenants WHERE unit_id = ? AND is_active = 1 ORDER BY full_name',
            [$unitId]
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            'SELECT * FROM tenants WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );
    }

    public static function create(int $accountId, int $unitId, array $data): int
    {
        return DB::insert(
            'INSERT INTO tenants (account_id, unit_id, full_name, email, phone, preferred_channel)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $accountId,
                $unitId,
                $data['full_name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['preferred_channel'] ?? 'email',
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE tenants SET full_name=?, email=?, phone=?, preferred_channel=?, reminders_paused=?, is_active=?
             WHERE id = ? AND account_id = ?',
            [
                $data['full_name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['preferred_channel'] ?? 'email',
                (int) ($data['reminders_paused'] ?? 0),
                (int) ($data['is_active'] ?? 1),
                $id,
                $accountId,
            ]
        );
    }

    public static function delete(int $id, int $accountId): void
    {
        DB::execute(
            'UPDATE tenants SET is_active = 0 WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );
    }

    public static function togglePause(int $id, int $accountId): void
    {
        DB::execute(
            'UPDATE tenants SET reminders_paused = NOT reminders_paused WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );
    }
}
