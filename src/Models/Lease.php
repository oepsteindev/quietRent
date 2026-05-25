<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Lease
{
    public static function create(int $tenantId, int $unitId, string $startDate, ?string $endDate = null): int
    {
        return DB::insert(
            'INSERT INTO leases (tenant_id, unit_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")',
            [$tenantId, $unitId, $startDate, $endDate]
        );
    }

    public static function generateCharges(int $unitId, int $tenantId, string $startDate, ?string $endDate = null): void
    {
        $unit = DB::fetchOne('SELECT * FROM units WHERE id = ?', [$unitId]);
        if (!$unit) return;

        $start = new \DateTime($startDate);
        $end = $endDate ? new \DateTime($endDate) : (new \DateTime())->modify('+24 months');

        $current = clone $start;
        $current->modify('first day of this month');

        while ($current <= $end) {
            $periodMonth = $current->format('Y-m');
            $dueDateObj = clone $current;
            $dueDateObj->setDate((int) $current->format('Y'), (int) $current->format('m'), min($unit['due_day'], 28));

            // Handle months with fewer days (e.g., February)
            if ((int) $dueDateObj->format('m') !== (int) $current->format('m')) {
                $dueDateObj->modify('last day of ' . $current->format('Y-m'));
            }

            $dueDate = $dueDateObj->format('Y-m-d');
            $lateFee = self::calculateLateFee($unit);

            RentCharge::generate($unitId, $tenantId, $periodMonth, $unit['monthly_rent_cents'], $dueDate, $lateFee);

            $current->modify('+1 month');
        }
    }

    private static function calculateLateFee(array $unit): int
    {
        if ($unit['late_fee_type'] === 'none') {
            return 0;
        }

        $lateFee = 0;
        if ($unit['late_fee_type'] === 'flat') {
            $lateFee = (int) round($unit['late_fee_value'] * 100);
        } elseif ($unit['late_fee_type'] === 'percent') {
            $lateFee = (int) round($unit['monthly_rent_cents'] * $unit['late_fee_value'] / 100);
        }

        if ($unit['late_fee_max_cents'] && $lateFee > $unit['late_fee_max_cents']) {
            $lateFee = $unit['late_fee_max_cents'];
        }

        return $lateFee;
    }
}
