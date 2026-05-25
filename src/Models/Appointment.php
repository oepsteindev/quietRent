<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Appointment
{
    public static function allForAccount(int $accountId, ?string $date = null, ?int $stylistId = null, ?string $status = null): array
    {
        $where  = ['a.account_id = ?'];
        $binds  = [$accountId];

        if ($date) {
            $where[] = 'DATE(a.appointment_at) = ?';
            $binds[] = $date;
        }
        if ($stylistId) {
            $where[] = 'a.stylist_id = ?';
            $binds[] = $stylistId;
        }
        if ($status) {
            $where[] = 'a.status = ?';
            $binds[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT a.*,
                    u.unit_label  as stylist_name,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    p.name        as salon_name
             FROM appointments a
             JOIN units u      ON u.id = a.stylist_id
             JOIN tenants t    ON t.id = a.client_id
             JOIN properties p ON p.id = u.property_id
             WHERE $whereClause
             ORDER BY a.appointment_at ASC",
            $binds
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            "SELECT a.*,
                    u.unit_label  as stylist_name,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    p.name        as salon_name
             FROM appointments a
             JOIN units u      ON u.id = a.stylist_id
             JOIN tenants t    ON t.id = a.client_id
             JOIN properties p ON p.id = u.property_id
             WHERE a.id = ? AND a.account_id = ?",
            [$id, $accountId]
        );
    }

    public static function create(int $accountId, array $data): int
    {
        return DB::insert(
            'INSERT INTO appointments (account_id, stylist_id, client_id, service_name, fee_cents, appointment_at, duration_minutes, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $accountId,
                (int) $data['stylist_id'],
                (int) $data['client_id'],
                $data['service_name'],
                (int) ($data['fee_cents'] ?? 0),
                $data['appointment_at'],
                (int) ($data['duration_minutes'] ?? 60),
                $data['notes'] ?? null,
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE appointments
             SET stylist_id=?, client_id=?, service_name=?, fee_cents=?,
                 appointment_at=?, duration_minutes=?, notes=?, status=?
             WHERE id = ? AND account_id = ?',
            [
                (int) $data['stylist_id'],
                (int) $data['client_id'],
                $data['service_name'],
                (int) ($data['fee_cents'] ?? 0),
                $data['appointment_at'],
                (int) ($data['duration_minutes'] ?? 60),
                $data['notes'] ?? null,
                $data['status'] ?? 'scheduled',
                $id,
                $accountId,
            ]
        );
    }

    public static function setStatus(int $id, int $accountId, string $status): void
    {
        DB::execute(
            'UPDATE appointments SET status = ? WHERE id = ? AND account_id = ?',
            [$status, $id, $accountId]
        );
    }

    /** Fetch appointments for a given month with payment info (for payment tracking view). */
    public static function forAccountByMonth(int $accountId, string $month, ?string $paymentStatus = null): array
    {
        $where  = ['a.account_id = ?', 'DATE_FORMAT(a.appointment_at, \'%Y-%m\') = ?'];
        $binds  = [$accountId, $month];

        if ($paymentStatus) {
            $where[] = 'a.payment_status = ?';
            $binds[] = $paymentStatus;
        }

        $whereClause = implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT a.id, a.appointment_at, a.service_name, a.fee_cents,
                    a.status, a.payment_status, a.paid_at,
                    u.unit_label as stylist_name,
                    t.full_name  as client_name,
                    t.id         as client_id,
                    p.name       as salon_name
             FROM appointments a
             JOIN units u      ON u.id = a.stylist_id
             JOIN tenants t    ON t.id = a.client_id
             JOIN properties p ON p.id = u.property_id
             WHERE $whereClause
             ORDER BY a.appointment_at ASC",
            $binds
        );
    }

    public static function markPaid(int $id, int $accountId): void
    {
        DB::execute(
            "UPDATE appointments SET payment_status = 'paid', paid_at = NOW() WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );
    }

    public static function waiveFee(int $id, int $accountId): void
    {
        DB::execute(
            "UPDATE appointments SET payment_status = 'waived' WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );
    }

    /** Fetch all upcoming scheduled appointments across all active accounts (for cron). */
    public static function upcomingScheduled(): array
    {
        return DB::fetchAll(
            "SELECT a.id as appointment_id, a.appointment_at, a.client_id,
                    t.reminders_paused, t.preferred_channel,
                    p.account_id
             FROM appointments a
             JOIN tenants t    ON t.id = a.client_id
             JOIN units u      ON u.id = a.stylist_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             WHERE a.status = 'scheduled'
               AND a.appointment_at > NOW()
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())"
        );
    }
}
