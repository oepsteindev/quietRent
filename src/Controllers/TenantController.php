<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\{Tenant, Unit};

class TenantController
{
    public function index(array $params): void
    {
        Auth::require();
        Response::json(Tenant::allForAccount(Auth::accountId()));
    }

    public function show(array $params): void
    {
        Auth::require();
        $tenant = Tenant::find((int) $params['id'], Auth::accountId());
        if (!$tenant) {
            Response::abort(404, 'Not found');
        }
        Response::json($tenant);
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $unitId = (int) ($body['unit_id'] ?? 0);

        if (!Unit::find($unitId, Auth::accountId())) {
            Response::abort(403, 'Unit not found');
        }

        $id = Tenant::create(Auth::accountId(), $unitId, $body);
        Response::json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id   = (int) $params['id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!Tenant::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }
        Tenant::update($id, Auth::accountId(), $body);
        Response::json(['ok' => true]);
    }

    public function destroy(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!Tenant::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }
        Tenant::delete($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    public function togglePause(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!Tenant::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }
        Tenant::togglePause($id, Auth::accountId());
        Response::json(['ok' => true]);
    }
}
