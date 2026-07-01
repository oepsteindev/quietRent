<?php

namespace QuietRent\Services;

use QuietRent\Core\DB;
use QuietRent\Models\Appointment;

class AppointmentReminderDispatcher
{
    private static array $stages = [
        ['stage' => 'reminder_30h', 'hours_before' => 30],
        ['stage' => 'reminder_2h',  'hours_before' => 2],
    ];

    /**
     * Schedule reminders for upcoming appointments that don't have them yet.
     */
    public static function scheduleAll(): int
    {
        $appointments = Appointment::upcomingScheduled();
        $now          = date('Y-m-d H:i:s');

        $count = 0;
        foreach ($appointments as $appt) {
            $channels = self::channels($appt['preferred_channel']);

            foreach (self::$stages as $s) {
                $scheduledAt = date(
                    'Y-m-d H:i:s',
                    strtotime("{$appt['appointment_at']} -{$s['hours_before']} hours")
                );

                if ($scheduledAt <= $now) {
                    continue; // reminder window already passed
                }

                foreach ($channels as $channel) {
                    $existing = DB::fetchOne(
                        'SELECT id FROM appointment_reminders WHERE appointment_id=? AND stage=? AND channel=?',
                        [$appt['appointment_id'], $s['stage'], $channel]
                    );
                    if ($existing) {
                        continue;
                    }

                    DB::execute(
                        'INSERT INTO appointment_reminders (appointment_id, stage, channel, scheduled_at, status)
                         VALUES (?, ?, ?, ?, "pending")',
                        [$appt['appointment_id'], $s['stage'], $channel, $scheduledAt]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Suppress pending reminders for canceled or no-show appointments.
     */
    public static function suppressCanceled(): int
    {
        return DB::execute(
            "UPDATE appointment_reminders ar
             JOIN appointments a ON a.id = ar.appointment_id
             SET ar.status = 'suppressed'
             WHERE ar.status = 'pending'
               AND a.status IN ('canceled','no_show')"
        );
    }

    /**
     * Send all pending appointment reminders that are due now.
     */
    public static function sendDue(): int
    {
        $hour = (int) date('G');
        if ($hour >= 22 || $hour < 6) {
            return 0;
        }

        $due = DB::fetchAll(
            "SELECT ar.*,
                    a.appointment_at, a.service_name, a.fee_cents,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    t.reminders_paused,
                    u.unit_label  as stylist_name,
                    p.name        as salon_name,
                    acc.name      as business_name,
                    acc.id        as account_id,
                    acc.disclaimer,
                    acc.payment_link,
                    acc.contact_phone,
                    arr.subject   as tpl_subject,
                    arr.body      as tpl_body
             FROM appointment_reminders ar
             JOIN appointments a   ON a.id = ar.appointment_id
             JOIN tenants t        ON t.id = a.client_id
             JOIN units u          ON u.id = a.stylist_id
             JOIN properties p     ON p.id = u.property_id
             JOIN accounts acc     ON acc.id = p.account_id
             LEFT JOIN appointment_reminder_rules arr
                    ON arr.account_id = acc.id AND arr.stage = ar.stage AND arr.is_active = 1
             WHERE ar.status = 'pending'
               AND ar.scheduled_at <= NOW()
               AND a.status = 'scheduled'
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())"
        );

        $sent = 0;
        foreach ($due as $r) {
            $success = self::dispatch($r);
            $status  = $success ? 'sent' : 'failed';

            DB::execute(
                'UPDATE appointment_reminders SET status=?, sent_at=NOW() WHERE id=?',
                [$status, $r['id']]
            );

            if ($success) {
                $sent++;
            }
        }

        return $sent;
    }

    private static function dispatch(array $r): bool
    {
        [$subject, $body] = self::render($r);

        if ($r['channel'] === 'sms') {
            if (!empty($r['client_phone'])) {
                return SMS::send($r['client_phone'], strip_tags($body));
            }
            return Mailer::send($r['client_email'], $subject, $body);
        }

        if ($r['channel'] === 'both') {
            $ok = Mailer::send($r['client_email'], $subject, $body);
            if (!empty($r['client_phone'])) {
                SMS::send($r['client_phone'], strip_tags($body));
            }
            return $ok;
        }

        return Mailer::send($r['client_email'], $subject, $body);
    }

    private static function render(array $r): array
    {
        $feeFormatted  = '$' . number_format(($r['fee_cents'] ?? 0) / 100, 2);
        $apptFormatted = date('l, F j \a\t g:i A', strtotime($r['appointment_at']));

        $clientEmail = $r['client_email'] ?? $r['email'] ?? '';
        $unsubUrl    = $clientEmail ? Mailer::unsubscribeUrl($clientEmail) : '';

        $vars = [
            '{client_name}'      => $r['client_name'],
            '{service_name}'     => $r['service_name'],
            '{appointment_at}'   => $apptFormatted,
            '{stylist_name}'     => $r['stylist_name'],
            '{salon_name}'       => $r['salon_name'],
            '{fee_amount}'       => $feeFormatted,
            '{business_name}'    => $r['business_name'],
            '{disclaimer}'       => $r['disclaimer'] ?? '',
            '{payment_link}'     => $r['payment_link'] ? 'Pay online here: ' . $r['payment_link'] : '',
            '{contact_phone}'    => $r['contact_phone'] ? 'For more information call ' . $r['contact_phone'] : '',
            '{unsubscribe_link}' => $unsubUrl,
        ];

        $subject = strtr($r['tpl_subject'] ?? $r['stage'], $vars);
        $body    = strtr($r['tpl_body']    ?? $r['stage'], $vars);
        if ($unsubUrl) {
            $body .= "\n\n---\nTo stop receiving these reminders: " . $unsubUrl;
        }

        return [$subject, $body];
    }

    /**
     * Send an immediate booking confirmation for a newly created appointment.
     * Called directly from the controller — does not go through the cron pipeline.
     */
    public static function sendConfirmation(int $appointmentId, int $accountId): bool
    {
        $row = DB::fetchOne(
            "SELECT a.appointment_at, a.service_name, a.fee_cents,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    u.unit_label  as stylist_name,
                    p.name        as salon_name,
                    acc.name      as business_name,
                    acc.disclaimer,
                    acc.payment_link,
                    acc.contact_phone,
                    arr.subject   as tpl_subject,
                    arr.body      as tpl_body
             FROM appointments a
             JOIN tenants t    ON t.id = a.client_id
             JOIN units u      ON u.id = a.stylist_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             LEFT JOIN appointment_reminder_rules arr
                    ON arr.account_id = acc.id AND arr.stage = 'confirmation' AND arr.is_active = 1
             WHERE a.id = ? AND a.account_id = ?",
            [$appointmentId, $accountId]
        );

        if (!$row) {
            return false;
        }

        return self::dispatch($row);
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
