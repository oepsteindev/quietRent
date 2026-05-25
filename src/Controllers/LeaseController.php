<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, DB};
use QuietRent\Models\{Unit, Tenant, Lease};

class LeaseController
{
    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $body     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $unitId   = (int) ($body['unit_id'] ?? 0);

        if (!Tenant::find($tenantId, Auth::accountId()) || !Unit::find($unitId, Auth::accountId())) {
            Response::abort(403, 'Not found');
        }

        $id = Lease::create($tenantId, $unitId, $body['start_date'], $body['end_date'] ?? null);
        Lease::generateCharges($unitId, $tenantId, $body['start_date'], $body['end_date'] ?? null);

        Response::json(['id' => $id], 201);
    }

    public function end(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        DB::execute(
            "UPDATE leases l
             JOIN tenants t ON t.id = l.tenant_id
             SET l.status = 'ended', l.end_date = CURDATE()
             WHERE l.id = ? AND t.account_id = ?",
            [$id, Auth::accountId()]
        );

        Response::json(['ok' => true]);
    }

    public function forTenant(array $params): void
    {
        Auth::require();
        $tenantId = (int) $params['id'];
        if (!Tenant::find($tenantId, Auth::accountId())) {
            Response::abort(403, 'Not found');
        }

        $leases = DB::fetchAll(
            'SELECT * FROM leases WHERE tenant_id = ? ORDER BY start_date DESC',
            [$tenantId]
        );

        Response::json($leases);
    }
}
