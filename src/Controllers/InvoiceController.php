<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Models\Invoice;
use QuietRent\Services\{InvoicePdf, Mailer};

class InvoiceController
{
    public function index(array $params): void
    {
        Auth::require();
        $status = $_GET['status'] ?? null;
        Response::json(Invoice::allForAccount(Auth::accountId(), $status));
    }

    public function show(array $params): void
    {
        Auth::require();
        $invoice = Invoice::find((int) $params['id'], Auth::accountId());
        if (!$invoice) {
            Response::json(['error' => 'Not found'], 404);
        }
        Response::json($invoice);
    }

    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['client_id'])) {
            Response::json(['error' => 'client_id is required'], 422);
        }

        $id = Invoice::create($accountId, $data);

        if (!empty($data['line_items'])) {
            Invoice::replaceLineItems($id, $data['line_items']);
        }

        Response::json(['ok' => true, 'id' => $id], 201);
    }

    public function update(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $id        = (int) $params['id'];

        if (!Invoice::find($id, $accountId)) {
            Response::json(['error' => 'Not found'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Invoice::update($id, $accountId, $data);

        if (isset($data['line_items'])) {
            Invoice::replaceLineItems($id, $data['line_items']);
        }

        Response::json(['ok' => true]);
    }

    public function send(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $id        = (int) $params['id'];

        $invoice = Invoice::find($id, $accountId);
        if (!$invoice) {
            Response::json(['error' => 'Not found'], 404);
        }

        if (empty($invoice['line_items'])) {
            Response::json(['error' => 'Cannot send an invoice with no line items'], 422);
        }

        $pdf      = InvoicePdf::generate($invoice);
        $filename = $invoice['invoice_number'] . '.pdf';
        $total    = '$' . number_format($invoice['total_cents'] / 100, 2);
        $due      = $invoice['due_date'] ? date('F j, Y', strtotime($invoice['due_date'])) : 'upon receipt';

        $body = "Hi {$invoice['client_name']},\n\n"
              . "Please find your invoice {$invoice['invoice_number']} attached.\n\n"
              . "  Amount due: {$total}\n"
              . "  Due: {$due}\n\n";

        if (!empty($invoice['payment_link'])) {
            $body .= "Pay online here: {$invoice['payment_link']}\n\n";
        }

        $body .= "Thank you,\n{$invoice['business_name']}";

        $ok = Mailer::sendWithPdf(
            $invoice['client_email'],
            "Invoice {$invoice['invoice_number']} from {$invoice['business_name']}",
            $body,
            $pdf,
            $filename
        );

        if (!$ok) {
            Response::json(['error' => 'Failed to send email'], 500);
        }

        Invoice::markSent($id, $accountId);
        Response::json(['ok' => true]);
    }

    public function markPaid(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];

        if (!Invoice::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }

        Invoice::markPaid($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    public function destroy(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $id = (int) $params['id'];

        if (!Invoice::find($id, Auth::accountId())) {
            Response::json(['error' => 'Not found'], 404);
        }

        Invoice::delete($id, Auth::accountId());
        Response::json(['ok' => true]);
    }

    public function download(array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        $invoice = Invoice::find($id, Auth::accountId());
        if (!$invoice) {
            Response::json(['error' => 'Not found'], 404);
        }

        $pdf      = InvoicePdf::generate($invoice);
        $filename = $invoice['invoice_number'] . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}
