<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\{Property, Unit};

class PropertyController
{
    public function index(array $params): void
    {
        Auth::require();
        Response::json(Property::allForAccount(Auth::accountId()));
    }

    public function show(array $params): void
    {
        Auth::require();
        $property = Property::find((int) $params['id'], Auth::accountId());
        if (!$property) {
            Response::abort(404, 'Not found');
        }
        $property['units'] = Unit::allForProperty($property['id']);
        Response::json($property);
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $data = $this->validated();
        $id   = Property::create(Auth::accountId(), $data);
        Response::json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!Property::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }

        $data = $this->validated();
        Property::update($id, Auth::accountId(), $data);
        Response::json(['ok' => true]);
    }

    public function destroy(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $id = (int) $params['id'];
        if (!Property::find($id, Auth::accountId())) {
            Response::abort(404, 'Not found');
        }
        Property::delete($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    private function validated(): array
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        return [
            'name'          => trim($body['name'] ?? ''),
            'address_line1' => trim($body['address_line1'] ?? ''),
            'address_line2' => trim($body['address_line2'] ?? '') ?: null,
            'city'          => trim($body['city'] ?? ''),
            'state'         => trim($body['state'] ?? ''),
            'zip'           => trim($body['zip'] ?? ''),
            'is_active'     => (int) ($body['is_active'] ?? 1),
        ];
    }
}
