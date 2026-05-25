<?php

namespace QuietRent\Services;

use QuietRent\Core\DB;

class ReminderDispatcher
{
    /**
     * Schedule reminders for all charges that don't have them yet.
     */
    public static function scheduleAll(): int
    {
        // Charges without a full reminder set and not paid, for active/trialing accounts only
        $charges = DB::fetchAll(
            "SELECT rc.id as charge_id, rc.due_date, rc.tenant_id, rc.status,
                    t.reminders_paused, t.preferred_channel,
                    p.account_id
             FROM rent_charges rc
             JOIN tenants t ON t.id = rc.tenant_id
             JOIN units u   ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             WHERE rc.status NOT IN ('paid','waived')
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())"
        );

        $stages = [
            ['stage' => 'pre_due', 'offset' => -3],
            ['stage' => 'due_day', 'offset' => 0],
            ['stage' => 'late_1',  'offset' => 1],
            ['stage' => 'late_5',  'offset' => 5],
        ];

        $count = 0;
        foreach ($charges as $charge) {
            $channels = self::channels($charge['preferred_channel']);

            foreach ($stages as $s) {
                $scheduledAt = date('Y-m-d 08:00:00', strtotime("{$charge['due_date']} {$s['offset']} days"));

                foreach ($channels as $channel) {
                    $existing = DB::fetchOne(
                        'SELECT id FROM reminders WHERE rent_charge_id=? AND stage=? AND channel=?',
                        [$charge['charge_id'], $s['stage'], $channel]
                    );
                    if ($existing) {
                        continue;
                    }

                    DB::execute(
                        'INSERT INTO reminders (rent_charge_id, stage, channel, scheduled_at, status)
                         VALUES (?, ?, ?, ?, "pending")',
                        [$charge['charge_id'], $s['stage'], $channel, $scheduledAt]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Send all pending reminders that are due now.
     */
    public static function sendDue(): int
    {
        $hour = (int) date('G');
        if ($hour >= 22 || $hour < 6) {
            return 0;
        }

        $due = DB::fetchAll(
            "SELECT r.*,
                    rc.due_date, rc.amount_cents, rc.late_fee_cents, rc.period_month,
                    t.full_name as tenant_name, t.email as tenant_email, t.phone as tenant_phone,
                    t.reminders_paused,
                    u.unit_label,
                    p.name as property_name,
                    acc.name as landlord_name,
                    acc.id as account_id,
                    acc.payment_link,
                    acc.contact_phone,
                    rr.subject as tpl_subject, rr.body as tpl_body
             FROM reminders r
             JOIN rent_charges rc ON rc.id = r.rent_charge_id
             JOIN tenants t ON t.id = rc.tenant_id
             JOIN units u   ON u.id = rc.unit_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             LEFT JOIN reminder_rules rr ON rr.account_id = acc.id AND rr.stage = r.stage
             WHERE r.status = 'pending'
               AND r.scheduled_at <= NOW()
               AND rc.status NOT IN ('paid','waived')
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())",
        );

        $sent = 0;
        foreach ($due as $r) {
            $success = self::dispatch($r);
            $status  = $success ? 'sent' : 'failed';

            DB::execute(
                'UPDATE reminders SET status=?, sent_at=NOW() WHERE id=?',
                [$status, $r['id']]
            );

            if ($success) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Suppress pending reminders for paid charges.
     */
    public static function suppressPaid(): int
    {
        return DB::execute(
            "UPDATE reminders r
             JOIN rent_charges rc ON rc.id = r.rent_charge_id
             SET r.status = 'suppressed'
             WHERE r.status = 'pending'
               AND rc.status IN ('paid','waived')"
        );
    }

    private static function dispatch(array $r): bool
    {
        [$subject, $body] = self::render($r);

        if ($r['channel'] === 'sms') {
            if (!empty($r['tenant_phone'])) {
                return SMS::send($r['tenant_phone'], strip_tags($body));
            }
            // Fallback to email if no phone number
            return Mailer::send($r['tenant_email'], $subject, $body);
        }

        // channel=both → email always, plus SMS if phone number exists
        if ($r['channel'] === 'both') {
            $ok = Mailer::send($r['tenant_email'], $subject, $body);
            if (!empty($r['tenant_phone'])) {
                SMS::send($r['tenant_phone'], strip_tags($body));
            }
            return $ok;
        }

        // default: email
        return Mailer::send($r['tenant_email'], $subject, $body);
    }

    private static function render(array $r): array
    {
        $rentFormatted    = '$' . number_format($r['amount_cents'] / 100, 2);
        $lateFeeFormatted = '$' . number_format($r['late_fee_cents'] / 100, 2);
        $totalDue         = '$' . number_format(($r['amount_cents'] + $r['late_fee_cents']) / 100, 2);

        $vars = [
            '{tenant_name}'     => $r['tenant_name'],
            '{property_name}'   => $r['property_name'],
            '{unit_label}'      => $r['unit_label'],
            '{rent_amount}'     => $rentFormatted,
            '{due_date}'        => $r['due_date'],
            '{late_fee_amount}' => $lateFeeFormatted,
            '{total_due}'       => $totalDue,
            '{payment_link}'    => $r['payment_link'] ? 'Pay online here: ' . $r['payment_link'] : '',
            '{contact_phone}'   => $r['contact_phone'] ? 'For more information call ' . $r['contact_phone'] : '',
            '{landlord_name}'   => $r['landlord_name'],
        ];

        $subject = strtr($r['tpl_subject'] ?? $r['stage'], $vars);
        $body    = strtr($r['tpl_body']    ?? $r['stage'], $vars);

        return [$subject, $body];
    }

    private static function channels(string $preferred): array
    {
        return match ($preferred) {
            'sms'   => ['sms'],
            'both'  => ['email', 'sms'],
            default => ['email'],
        };
    }
}

function env(string $key, mixed $default = null): mixed
{
    return \QuietRent\Core\Env::get($key, $default);
}
