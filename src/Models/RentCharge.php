<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class RentCharge
{
    public static function generate(int $unitId, int $tenantId, string $periodMonth, int $amountCents, string $dueDate, int $lateFee = 0): int
    {
        return DB::insert(
            'INSERT IGNORE INTO rent_charges
             (unit_id, tenant_id, period_month, amount_cents, due_date, late_fee_cents, status)
             VALUES (?, ?, ?, ?, ?, ?, "upcoming")',
            [$unitId, $tenantId, $periodMonth, $amountCents, $dueDate, $lateFee]
        );
    }

    public static function forAccount(int $accountId, ?string $periodMonth = null): array
    {
        $where = $periodMonth ? 'AND rc.period_month = ?' : '';
        $params = $periodMonth
            ? [$accountId, $periodMonth]
            : [$accountId];

        $charges = DB::fetchAll(
            "SELECT rc.*, t.full_name as tenant_name, t.email as tenant_email,
                    u.unit_label, p.name as property_name, p.id as property_id
             FROM rent_charges rc
             JOIN tenants t ON t.id = rc.tenant_id
             JOIN units u   ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? $where
             ORDER BY rc.due_date DESC, p.name, u.unit_label",
            $params
        );

        if (!$charges) {
            return $charges;
        }

        $ids = array_column($charges, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $reminders = DB::fetchAll(
            "SELECT rent_charge_id, stage, channel, sent_at, status
             FROM reminders
             WHERE rent_charge_id IN ($placeholders) AND status IN ('sent', 'failed')
             ORDER BY sent_at ASC",
            $ids
        );

        $byCharge = [];
        foreach ($reminders as $r) {
            $byCharge[$r['rent_charge_id']][] = [
                'stage'   => $r['stage'],
                'channel' => $r['channel'],
                'sent_at' => $r['sent_at'],
                'status'  => $r['status'],
            ];
        }

        foreach ($charges as &$charge) {
            $charge['alerts_sent'] = $byCharge[$charge['id']] ?? [];
        }

        return $charges;
    }

    public static function find(int $id, int $accountId): array|false
    {
        return DB::fetchOne(
            'SELECT rc.*, t.full_name as tenant_name, t.email as tenant_email,
                    u.unit_label, p.name as property_name, p.account_id
             FROM rent_charges rc
             JOIN tenants t ON t.id = rc.tenant_id
             JOIN units u   ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE rc.id = ? AND p.account_id = ?',
            [$id, $accountId]
        );
    }

    public static function markPaid(int $id, int $accountId): void
    {
        DB::execute(
            'UPDATE rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             SET rc.status = "paid", rc.paid_at = NOW()
             WHERE rc.id = ? AND p.account_id = ? AND rc.status != "paid"',
            [$id, $accountId]
        );
    }

    public static function applyLateFee(int $id, int $lateFee): void
    {
        DB::execute(
            'UPDATE rent_charges SET late_fee_cents = ?, status = "late"
             WHERE id = ? AND status != "paid"',
            [$lateFee, $id]
        );
    }

    public static function updateStatuses(): void
    {
        // upcoming → due when due_date is today
        DB::execute(
            "UPDATE rent_charges SET status = 'due'
             WHERE status = 'upcoming' AND due_date <= CURDATE()"
        );

        // due → late after grace period (stored on unit)
        DB::execute(
            "UPDATE rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             SET rc.status = 'late'
             WHERE rc.status = 'due'
               AND CURDATE() > DATE_ADD(rc.due_date, INTERVAL u.grace_days DAY)"
        );
    }

    public static function dashboardSummary(int $accountId): array
    {
        $month = date('Y-m');

        $collected = DB::fetchOne(
            'SELECT COALESCE(SUM(rc.amount_cents), 0) as total
             FROM rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? AND rc.period_month = ? AND rc.status = "paid"',
            [$accountId, $month]
        );

        $outstanding = DB::fetchOne(
            'SELECT COALESCE(SUM(rc.amount_cents + rc.late_fee_cents), 0) as total
             FROM rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? AND rc.period_month = ? AND rc.status != "paid" AND rc.status != "waived"',
            [$accountId, $month]
        );

        $lateCount = DB::fetchOne(
            'SELECT COUNT(*) as cnt
             FROM rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? AND rc.status = "late"',
            [$accountId]
        );

        $upcoming = DB::fetchOne(
            'SELECT COUNT(*) as cnt
             FROM rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? AND rc.status = "upcoming"
               AND rc.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            [$accountId]
        );

        return [
            'collected'   => (int) $collected['total'],
            'outstanding' => (int) $outstanding['total'],
            'late_count'  => (int) $lateCount['cnt'],
            'upcoming'    => (int) $upcoming['cnt'],
        ];
    }
}
