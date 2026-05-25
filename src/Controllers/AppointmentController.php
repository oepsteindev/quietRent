<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\Appointment;
use QuietRent\Services\AppointmentReminderDispatcher;

class AppointmentController
{
    public function index(array $params): void
    {
        Auth::require();
        $accountId = Auth::accountId();

        $date      = $_GET['date']       ?? null;
        $stylistId = isset($_GET['stylist_id']) ? (int) $_GET['stylist_id'] : null;
        $status    = $_GET['status']     ?? null;

        Response::json(Appointment::allForAccount($accountId, $date, $stylistId, $status));
    }

    public function show(array $params): void
    {
        Auth::require();
        $appt = Appointment::find((int) $params['id'], Auth::accountId());
        if (!$appt) {
            Response::json(['error' => 'Not found'], 404);
        }
        Response::json($appt);
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['stylist_id']) || empty($data['client_id']) ||
            empty($data['service_name']) || empty($data['appointment_at'])) {
            Response::json(['error' => 'stylist_id, client_id, service_name, and appointment_at are required'], 422);
        }

        $id = Appointment::create($accountId, $data);
        AppointmentReminderDispatcher::sendConfirmation($id, $accountId);
        Response::json(['ok' => true, 'id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $id        = (int) $params['id'];

        if (!Appointment::find($id, $accountId)) {
            Response::json(['error' => 'Not found'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Appointment::update($id, $accountId, $data);
        Response::json(['ok' => true]);
    }

    public function cancel(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Appointment::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Appointment::setStatus($id, Auth::accountId(), 'canceled');
        Response::json(['ok' => true]);
    }

    public function complete(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Appointment::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Appointment::setStatus($id, Auth::accountId(), 'completed');
        Response::json(['ok' => true]);
    }

    public function noShow(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Appointment::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Appointment::setStatus($id, Auth::accountId(), 'no_show');
        Response::json(['ok' => true]);
    }

    public function payments(array $params): void
    {
        Auth::require();
        $month         = $_GET['month']          ?? date('Y-m');
        $paymentStatus = $_GET['payment_status'] ?? null;
        Response::json(Appointment::forAccountByMonth(Auth::accountId(), $month, $paymentStatus));
    }

    public function markPaid(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Appointment::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Appointment::markPaid($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    public function waiveFee(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Appointment::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Appointment::waiveFee($id, Auth::accountId());
        Response::json(['ok' => true]);
    }
}
