<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};
use QuietRent\Models\RentCharge;

class RentController
{
    public function index(array $params): void
    {
        Auth::require();
        $month = $_GET['month'] ?? date('Y-m');
        Response::json(RentCharge::forAccount(Auth::accountId(), $month));
    }

    public function markPaid(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!RentCharge::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }

        RentCharge::markPaid($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    public function waive(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!RentCharge::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }

        DB::execute(
            "UPDATE rent_charges rc
             JOIN units u ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             SET rc.status = 'waived'
             WHERE rc.id = ? AND p.account_id = ?",
            [$id, Auth::accountId()]
        );

        Response::json(['ok' => true]);
    }
}
