<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Invoice
{
    public static function allForAccount(int $accountId, ?string $status = null): array
    {
        $where = ['i.account_id = ?'];
        $binds = [$accountId];

        if ($status) {
            $where[] = 'i.status = ?';
            $binds[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT i.*,
                    t.full_name  as client_name,
                    t.email      as client_email,
                    COALESCE(SUM(ROUND(li.quantity * li.unit_price_cents)), 0) as total_cents
             FROM invoices i
             JOIN tenants t ON t.id = i.client_id
             LEFT JOIN invoice_line_items li ON li.invoice_id = i.id
             WHERE $whereClause
             GROUP BY i.id
             ORDER BY i.created_at DESC",
            $binds
        );
    }

    public static function find(int $id, int $accountId): array|false
    {
        $invoice = DB::fetchOne(
            "SELECT i.*,
                    t.full_name  as client_name,
                    t.email      as client_email,
                    t.phone      as client_phone,
                    acc.name     as business_name,
                    acc.contact_phone as business_phone,
                    acc.payment_link
             FROM invoices i
             JOIN tenants t  ON t.id = i.client_id
             JOIN accounts acc ON acc.id = i.account_id
             WHERE i.id = ? AND i.account_id = ?",
            [$id, $accountId]
        );

        if (!$invoice) {
            return false;
        }

        $invoice['line_items'] = DB::fetchAll(
            'SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY id',
            [$id]
        );

        $invoice['total_cents'] = array_reduce($invoice['line_items'], function ($carry, $item) {
            return $carry + (int) round($item['quantity'] * $item['unit_price_cents']);
        }, 0);

        return $invoice;
    }

    public static function create(int $accountId, array $data): int
    {
        $seq = self::nextSequence($accountId);
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        return DB::insert(
            'INSERT INTO invoices (account_id, client_id, job_id, invoice_number, due_date, notes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $accountId,
                (int) $data['client_id'],
                !empty($data['job_id']) ? (int) $data['job_id'] : null,
                $invoiceNumber,
                $data['due_date'] ?: null,
                $data['notes'] ?? null,
            ]
        );
    }

    public static function update(int $id, int $accountId, array $data): void
    {
        DB::execute(
            'UPDATE invoices SET client_id=?, job_id=?, due_date=?, notes=? WHERE id=? AND account_id=?',
            [
                (int) $data['client_id'],
                !empty($data['job_id']) ? (int) $data['job_id'] : null,
                $data['due_date'] ?: null,
                $data['notes'] ?? null,
                $id,
                $accountId,
            ]
        );
    }

    public static function replaceLineItems(int $invoiceId, array $items): void
    {
        DB::execute('DELETE FROM invoice_line_items WHERE invoice_id = ?', [$invoiceId]);

        foreach ($items as $item) {
            DB::execute(
                'INSERT INTO invoice_line_items (invoice_id, description, quantity, unit_price_cents)
                 VALUES (?, ?, ?, ?)',
                [
                    $invoiceId,
                    $item['description'],
                    (float) ($item['quantity'] ?? 1),
                    (int) ($item['unit_price_cents'] ?? 0),
                ]
            );
        }
    }

    public static function markSent(int $id, int $accountId): void
    {
        DB::execute(
            "UPDATE invoices SET status='sent', sent_at=NOW() WHERE id=? AND account_id=?",
            [$id, $accountId]
        );
    }

    public static function markPaid(int $id, int $accountId): void
    {
        DB::execute(
            "UPDATE invoices SET status='paid', paid_at=NOW() WHERE id=? AND account_id=?",
            [$id, $accountId]
        );
    }

    public static function delete(int $id, int $accountId): void
    {
        DB::execute('DELETE FROM invoices WHERE id=? AND account_id=?', [$id, $accountId]);
    }

    private static function nextSequence(int $accountId): int
    {
        $row = DB::fetchOne(
            'SELECT COUNT(*) as n FROM invoices WHERE account_id = ?',
            [$accountId]
        );
        return (int) ($row['n'] ?? 0) + 1;
    }
}
