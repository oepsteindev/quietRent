<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\{RentCharge, Property};

class DashboardController
{
    public function index(array $params): void
    {
        Auth::require();
        // All dashboard data is served via API - just render the SPA shell
        require __DIR__ . '/../../public/shell.php';
    }

    public function data(array $params): void
    {
        Auth::require();
        $accountId = Auth::accountId();

        $summary    = RentCharge::dashboardSummary($accountId);
        $properties = Property::allForAccount($accountId);

        $lateCharges = \QuietRent\Core\DB::fetchAll(
            "SELECT rc.id, rc.period_month, rc.amount_cents, rc.late_fee_cents, rc.due_date,
                    t.full_name as tenant_name, u.unit_label, p.name as property_name
             FROM rent_charges rc
             JOIN tenants t ON t.id = rc.tenant_id
             JOIN units u   ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             WHERE p.account_id = ? AND rc.status = 'late'
             ORDER BY rc.due_date ASC",
            [$accountId]
        );

        $account = \QuietRent\Models\Account::find($accountId);

        Response::json([
            'summary'        => $summary,
            'late_charges'   => $lateCharges,
            'property_count' => count($properties),
            'product_type'   => $account['product_type'] ?? 'landlords',
        ]);
    }
}
