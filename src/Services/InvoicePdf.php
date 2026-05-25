<?php

namespace QuietRent\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdf
{
    public static function generate(array $invoice): string
    {
        $html = self::render($invoice);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function render(array $inv): string
    {
        $business   = htmlspecialchars($inv['business_name'] ?? '');
        $bizPhone   = htmlspecialchars($inv['business_phone'] ?? '');
        $payLink    = htmlspecialchars($inv['payment_link'] ?? '');
        $clientName = htmlspecialchars($inv['client_name'] ?? '');
        $clientEmail= htmlspecialchars($inv['client_email'] ?? '');
        $invNum     = htmlspecialchars($inv['invoice_number'] ?? '');
        $dueDate    = $inv['due_date'] ? date('F j, Y', strtotime($inv['due_date'])) : 'Upon receipt';
        $issueDate  = date('F j, Y', strtotime($inv['created_at']));
        $notes      = nl2br(htmlspecialchars($inv['notes'] ?? ''));
        $status     = strtoupper($inv['status'] ?? 'DRAFT');

        $statusColor = match ($inv['status'] ?? 'draft') {
            'paid'  => '#10b981',
            'sent'  => '#3b82f6',
            default => '#94a3b8',
        };

        $lineRows = '';
        $total = 0;
        foreach ($inv['line_items'] ?? [] as $item) {
            $lineTotal = (int) round($item['quantity'] * $item['unit_price_cents']);
            $total += $lineTotal;
            $lineRows .= sprintf(
                '<tr>
                    <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">%s</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:center;">%s</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">%s</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">%s</td>
                </tr>',
                htmlspecialchars($item['description']),
                number_format((float) $item['quantity'], 2),
                '$' . number_format($item['unit_price_cents'] / 100, 2),
                '$' . number_format($lineTotal / 100, 2)
            );
        }

        $totalFormatted = '$' . number_format($total / 100, 2);

        $paySection = '';
        if ($payLink) {
            $paySection = '<p style="margin:6px 0 0;font-size:12px;color:#64748b;">Pay online: <span style="color:#3b82f6;">' . $payLink . '</span></p>';
        }
        if ($bizPhone) {
            $paySection .= '<p style="margin:6px 0 0;font-size:12px;color:#64748b;">Questions? Call ' . $bizPhone . '</p>';
        }

        $notesSection = $notes
            ? '<div class="notes"><h3>Notes</h3><p>' . $notes . '</p></div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1e293b; background: #fff; }
  .page { padding: 48px; }
  .header { display: table; width: 100%; margin-bottom: 40px; }
  .header-left  { display: table-cell; vertical-align: top; }
  .header-right { display: table-cell; vertical-align: top; text-align: right; }
  .business-name { font-size: 22px; font-weight: bold; color: #0f172a; }
  .business-sub  { font-size: 12px; color: #64748b; margin-top: 4px; }
  .invoice-label { font-size: 28px; font-weight: bold; color: #0f172a; }
  .status-badge {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-size: 11px; font-weight: bold; color: #fff;
    background: {$statusColor}; margin-top: 6px;
  }
  .meta-table { width: 100%; margin-bottom: 32px; }
  .meta-table td { padding: 4px 0; font-size: 12px; color: #64748b; }
  .meta-table .label { font-weight: bold; color: #334155; width: 120px; }
  .bill-to { margin-bottom: 32px; }
  .bill-to h3 { font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .bill-to p { font-size: 13px; color: #1e293b; }
  .line-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .line-table thead th {
    background: #f8fafc; padding: 10px 12px;
    font-size: 11px; font-weight: bold; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
  }
  .line-table thead th:last-child  { text-align: right; }
  .line-table thead th:nth-child(2){ text-align: center; }
  .line-table thead th:nth-child(3){ text-align: right; }
  .total-row td { padding: 14px 12px; font-size: 15px; font-weight: bold; color: #0f172a; border-top: 2px solid #e2e8f0; }
  .notes { background: #f8fafc; border-radius: 6px; padding: 14px 16px; margin-top: 24px; }
  .notes h3 { font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .notes p { font-size: 12px; color: #475569; line-height: 1.6; }
  .footer { margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
</style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="header-left">
      <div class="business-name">{$business}</div>
      <div class="business-sub">{$bizPhone}</div>
    </div>
    <div class="header-right">
      <div class="invoice-label">INVOICE</div>
      <div><span class="status-badge">{$status}</span></div>
    </div>
  </div>

  <table class="meta-table">
    <tr>
      <td class="label">Invoice #</td>
      <td>{$invNum}</td>
      <td></td>
    </tr>
    <tr>
      <td class="label">Issue Date</td>
      <td>{$issueDate}</td>
      <td></td>
    </tr>
    <tr>
      <td class="label">Due Date</td>
      <td>{$dueDate}</td>
      <td></td>
    </tr>
  </table>

  <div class="bill-to">
    <h3>Bill To</h3>
    <p>{$clientName}</p>
    <p style="color:#64748b;font-size:12px;">{$clientEmail}</p>
  </div>

  <table class="line-table">
    <thead>
      <tr>
        <th style="text-align:left;">Description</th>
        <th>Qty</th>
        <th>Unit Price</th>
        <th>Amount</th>
      </tr>
    </thead>
    <tbody>
      {$lineRows}
      <tr class="total-row">
        <td colspan="3" style="text-align:right;padding-right:12px;">Total</td>
        <td style="text-align:right;">{$totalFormatted}</td>
      </tr>
    </tbody>
  </table>

  {$notesSection}

  <div class="footer">
    {$paySection}
    <p style="font-size:11px;color:#94a3b8;margin-top:12px;">Thank you for your business.</p>
  </div>
</div>
</body>
</html>
HTML;
    }
}
