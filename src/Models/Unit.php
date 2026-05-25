<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Unit
{
    public static function allForProperty(int $propertyId): array
    {
        return DB::fetchAll(
            'SELECT u.*,
                    t.full_name as tenant_name,
                    t.id as tenant_id,
                    l.status as lease_status
             FROM units u
             LEFT JOIN tenants t ON t.unit_id = u.id AND t.is_active = 1
             LEFT JOIN leases l ON l.unit_id = u.id AND l.status = "active"
             WHERE u.property_id = ?
             ORDER BY u.unit_label',
            [$propertyId]
        );
    }

    public static function allForAccount(int $accountId): array
    {
        return DB::fetchAll(
            'SELECT u.*, p.name as property_name
             FROM units u
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ?
             ORDER BY p.name, u.unit_label',
            [$accountId]
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            'SELECT u.* FROM units u
             JOIN properties p ON p.id = u.property_id
             WHERE u.id = ? AND p.account_id = ?',
            [$id, $accountId]
        );
    }

    public static function create(int $propertyId, array $data): int
    {
        return DB::insert(
            'INSERT INTO units (property_id, unit_label, monthly_rent_cents, due_day, grace_days,
                                late_fee_type, late_fee_value, late_fee_max_cents)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $propertyId,
                $data['unit_label'],
                (int) round((float)($data['monthly_rent'] ?? 0) * 100),
                (int) ($data['due_day'] ?? 1),
                (int) ($data['grace_days'] ?? 5),
                $data['late_fee_type'] ?? 'none',
                (float) ($data['late_fee_value'] ?? 0),
                isset($data['late_fee_max']) ? (int) round($data['late_fee_max'] * 100) : null,
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE units u
             JOIN properties p ON p.id = u.property_id
             SET u.unit_label=?, u.monthly_rent_cents=?, u.due_day=?, u.grace_days=?,
                 u.late_fee_type=?, u.late_fee_value=?, u.late_fee_max_cents=?, u.is_active=?
             WHERE u.id = ? AND p.account_id = ?',
            [
                $data['unit_label'],
                (int) round((float)($data['monthly_rent'] ?? 0) * 100),
                (int) $data['due_day'],
                (int) $data['grace_days'],
                $data['late_fee_type'] ?? 'none',
                (float) ($data['late_fee_value'] ?? 0),
                isset($data['late_fee_max']) ? (int) round($data['late_fee_max'] * 100) : null,
                $data['is_active'] ?? 1,
                $id,
                $accountId,
            ]
        );
    }

    public static function delete(int $id, int $accountId): void
    {
        DB::execute(
            'DELETE u FROM units u
             JOIN properties p ON p.id = u.property_id
             WHERE u.id = ? AND p.account_id = ?',
            [$id, $accountId]
        );
    }
}
