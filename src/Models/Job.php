<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Job
{
    public static function allForAccount(int $accountId, ?string $date = null, ?int $tradesmanId = null, ?string $status = null): array
    {
        $where = ['j.account_id = ?'];
        $binds = [$accountId];

        if ($date) {
            $where[] = 'DATE(j.scheduled_at) = ?';
            $binds[] = $date;
        }
        if ($tradesmanId) {
            $where[] = 'j.tradesman_id = ?';
            $binds[] = $tradesmanId;
        }
        if ($status) {
            $where[] = 'j.status = ?';
            $binds[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT j.*,
                    u.unit_label  as tradesman_name,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    p.name        as company_name
             FROM jobs j
             JOIN units u      ON u.id = j.tradesman_id
             JOIN tenants t    ON t.id = j.client_id
             JOIN properties p ON p.id = u.property_id
             WHERE $whereClause
             ORDER BY j.scheduled_at ASC",
            $binds
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            "SELECT j.*,
                    u.unit_label  as tradesman_name,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    p.name        as company_name
             FROM jobs j
             JOIN units u      ON u.id = j.tradesman_id
             JOIN tenants t    ON t.id = j.client_id
             JOIN properties p ON p.id = u.property_id
             WHERE j.id = ? AND j.account_id = ?",
            [$id, $accountId]
        );
    }

    public static function create(int $accountId, array $data): int
    {
        return DB::insert(
            'INSERT INTO jobs (account_id, tradesman_id, client_id, job_type, estimated_cost_cents, address, scheduled_at, duration_minutes, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $accountId,
                (int) $data['tradesman_id'],
                (int) $data['client_id'],
                $data['job_type'],
                (int) ($data['estimated_cost_cents'] ?? 0),
                $data['address'] ?? null,
                $data['scheduled_at'],
                (int) ($data['duration_minutes'] ?? 60),
                $data['notes'] ?? null,
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE jobs
             SET tradesman_id=?, client_id=?, job_type=?, estimated_cost_cents=?,
                 address=?, scheduled_at=?, duration_minutes=?, notes=?, status=?
             WHERE id = ? AND account_id = ?',
            [
                (int) $data['tradesman_id'],
                (int) $data['client_id'],
                $data['job_type'],
                (int) ($data['estimated_cost_cents'] ?? 0),
                $data['address'] ?? null,
                $data['scheduled_at'],
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
            'UPDATE jobs SET status = ? WHERE id = ? AND account_id = ?',
            [$status, $id, $accountId]
        );
    }

    /** Fetch all upcoming scheduled jobs across active accounts (for cron). */
    public static function upcomingScheduled(): array
    {
        return DB::fetchAll(
            "SELECT j.id as job_id, j.scheduled_at, j.client_id,
                    t.reminders_paused, t.preferred_channel,
                    p.account_id
             FROM jobs j
             JOIN tenants t    ON t.id = j.client_id
             JOIN units u      ON u.id = j.tradesman_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             WHERE j.status = 'scheduled'
               AND j.scheduled_at > NOW()
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())"
        );
    }
}
