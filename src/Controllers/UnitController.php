<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\{Unit, Property};

class UnitController
{
    public function index(array $params): void
    {
        Auth::require();
        Response::json(Unit::allForAccount(Auth::accountId()));
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $propertyId = (int) ($body['property_id'] ?? 0);

        if (!Property::find($propertyId, Auth::accountId())) {
            Response::abort(403, 'Property not found');
        }

        $id = Unit::create($propertyId, $body);
        Response::json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id   = (int) $params['id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!Unit::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }

        Unit::update($id, Auth::accountId(), $body);
        Response::json(['ok' => true]);
    }

    public function destroy(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!Unit::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }
        Unit::delete($id, Auth::accountId());
        Response::json(['ok' => true]);
    }
}
