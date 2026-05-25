<?php

namespace QuietRent\Services;

use QuietRent\Core\DB;
use QuietRent\Models\RentCharge;

/**
 * Generates rent charges for active leases.
 * Idempotent: uses INSERT IGNORE on (unit_id, tenant_id, period_month).
 */
class BillingEngine
{
    /**
     * Generate charges for the given month (default: current month).
     * Run once per day via cron — safe to run multiple times.
     */
    public static function generateMonth(string $month = null): int
    {
        $month = $month ?? date('Y-m');
        [$year, $mon] = explode('-', $month);
        $created = 0;

        // All active leases with occupied units
        $leases = DB::fetchAll(
            "SELECT l.id, l.tenant_id, l.unit_id,
                    u.monthly_rent_cents, u.due_day,
                    u.late_fee_type, u.late_fee_value, u.late_fee_max_cents
             FROM leases l
             JOIN units u ON u.id = l.unit_id
             WHERE l.status = 'active'
               AND l.start_date <= LAST_DAY(?)
               AND (l.end_date IS NULL OR l.end_date >= ?)",
            ["$month-01", "$month-01"]
        );

        foreach ($leases as $lease) {
            $dueDay  = min((int) $lease['due_day'], (int) date('t', mktime(0, 0, 0, $mon, 1, $year)));
            $dueDate = sprintf('%04d-%02d-%02d', $year, $mon, $dueDay);

            $newId = RentCharge::generate(
                $lease['unit_id'],
                $lease['tenant_id'],
                $month,
                $lease['monthly_rent_cents'],
                $dueDate
            );

            if ($newId > 0) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Apply late fees to overdue charges that don't have one yet.
     */
    public static function applyLateFees(): int
    {
        $charges = DB::fetchAll(
            "SELECT rc.id, rc.amount_cents, rc.due_date,
                    u.grace_days, u.late_fee_type, u.late_fee_value, u.late_fee_max_cents
             FROM rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             WHERE rc.status = 'late'
               AND rc.late_fee_cents = 0
               AND u.late_fee_type != 'none'"
        );

        $count = 0;
        foreach ($charges as $charge) {
            $fee = self::calculateFee($charge);
            if ($fee > 0) {
                RentCharge::applyLateFee($charge['id'], $fee);
                $count++;
            }
        }

        return $count;
    }

    private static function calculateFee(array $charge): int
    {
        $base = $charge['amount_cents'];
        $fee  = 0;

        if ($charge['late_fee_type'] === 'flat') {
            $fee = (int) round($charge['late_fee_value'] * 100);
        } elseif ($charge['late_fee_type'] === 'percent') {
            $fee = (int) round($base * ($charge['late_fee_value'] / 100));
        }

        if ($charge['late_fee_max_cents'] && $fee > $charge['late_fee_max_cents']) {
            $fee = $charge['late_fee_max_cents'];
        }

        return $fee;
    }
}
