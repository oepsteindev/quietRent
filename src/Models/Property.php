<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Property
{
    public static function allForAccount(int $accountId): array
    {
        return DB::fetchAll(
            'SELECT p.*, COUNT(u.id) as unit_count
             FROM properties p
             LEFT JOIN units u ON u.property_id = p.id
             WHERE p.account_id = ?
             GROUP BY p.id
             ORDER BY p.name',
            [$accountId]
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            'SELECT * FROM properties WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );
    }

    public static function create(int $accountId, array $data): int
    {
        return DB::insert(
            'INSERT INTO properties (account_id, name, address_line1, address_line2, city, state, zip)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $accountId,
                $data['name'],
                $data['address_line1'],
                $data['address_line2'] ?? null,
                $data['city'],
                $data['state'],
                $data['zip'],
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE properties SET name=?, address_line1=?, address_line2=?, city=?, state=?, zip=?, is_active=?
             WHERE id = ? AND account_id = ?',
            [
                $data['name'],
                $data['address_line1'],
                $data['address_line2'] ?? null,
                $data['city'],
                $data['state'],
                $data['zip'],
                $data['is_active'] ?? 1,
                $id,
                $accountId,
            ]
        );
    }

    public static function delete(int $id, int $accountId): void
    {
        DB::execute('DELETE FROM properties WHERE id = ? AND account_id = ?', [$id, $accountId]);
    }
}
