<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\Job;
use QuietRent\Services\JobReminderDispatcher;

class JobController
{
    public function index(array $params): void
    {
        Auth::require();
        $accountId   = Auth::accountId();
        $date        = $_GET['date']         ?? null;
        $tradesmanId = isset($_GET['tradesman_id']) ? (int) $_GET['tradesman_id'] : null;
        $status      = $_GET['status']       ?? null;

        Response::json(Job::allForAccount($accountId, $date, $tradesmanId, $status));
    }

    public function show(array $params): void
    {
        Auth::require();
        $job = Job::find((int) $params['id'], Auth::accountId());
        if (!$job) {
            Response::json(['error' => 'Not found'], 404);
        }
        Response::json($job);
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['tradesman_id']) || empty($data['client_id']) ||
            empty($data['job_type']) || empty($data['scheduled_at'])) {
            Response::json(['error' => 'tradesman_id, client_id, job_type, and scheduled_at are required'], 422);
        }

        $id = Job::create($accountId, $data);
        JobReminderDispatcher::sendConfirmation($id, $accountId);
        Response::json(['ok' => true, 'id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $id        = (int) $params['id'];

        if (!Job::find($id, $accountId)) {
            Response::json(['error' => 'Not found'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Job::update($id, $accountId, $data);
        Response::json(['ok' => true]);
    }

    public function cancel(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Job::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Job::setStatus($id, Auth::accountId(), 'canceled');
        Response::json(['ok' => true]);
    }

    public function complete(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Job::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Job::setStatus($id, Auth::accountId(), 'completed');
        Response::json(['ok' => true]);
    }

    public function noShow(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];
        if (!Job::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }
        Job::setStatus($id, Auth::accountId(), 'no_show');
        Response::json(['ok' => true]);
    }
}
