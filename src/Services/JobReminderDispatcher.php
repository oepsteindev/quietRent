<?php

namespace QuietRent\Services;

use QuietRent\Core\DB;
use QuietRent\Models\Job;

class JobReminderDispatcher
{
    private static array $stages = [
        ['stage' => 'reminder_24h', 'hours_before' => 24],
        ['stage' => 'reminder_2h',  'hours_before' => 2],
    ];

    public static function scheduleAll(): int
    {
        $jobs = Job::upcomingScheduled();
        $now  = date('Y-m-d H:i:s');

        $count = 0;
        foreach ($jobs as $job) {
            $channels = self::channels($job['preferred_channel']);

            foreach (self::$stages as $s) {
                $scheduledAt = date(
                    'Y-m-d H:i:s',
                    strtotime("{$job['scheduled_at']} -{$s['hours_before']} hours")
                );

                if ($scheduledAt <= $now) {
                    continue;
                }

                foreach ($channels as $channel) {
                    $existing = DB::fetchOne(
                        'SELECT id FROM job_reminders WHERE job_id=? AND stage=? AND channel=?',
                        [$job['job_id'], $s['stage'], $channel]
                    );
                    if ($existing) {
                        continue;
                    }

                    DB::execute(
                        'INSERT INTO job_reminders (job_id, stage, channel, scheduled_at, status)
                         VALUES (?, ?, ?, ?, "pending")',
                        [$job['job_id'], $s['stage'], $channel, $scheduledAt]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    public static function suppressCanceled(): int
    {
        return DB::execute(
            "UPDATE job_reminders jr
             JOIN jobs j ON j.id = jr.job_id
             SET jr.status = 'suppressed'
             WHERE jr.status = 'pending'
               AND j.status IN ('canceled','no_show')"
        );
    }

    public static function sendDue(): int
    {
        $hour = (int) date('G');
        if ($hour >= 22 || $hour < 6) {
            return 0;
        }

        $due = DB::fetchAll(
            "SELECT jr.*,
                    j.scheduled_at, j.job_type, j.estimated_cost_cents, j.address,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    t.reminders_paused,
                    u.unit_label  as tradesman_name,
                    p.name        as company_name,
                    acc.name      as business_name,
                    acc.id        as account_id,
                    acc.payment_link,
                    acc.contact_phone,
                    jrr.subject   as tpl_subject,
                    jrr.body      as tpl_body
             FROM job_reminders jr
             JOIN jobs j       ON j.id = jr.job_id
             JOIN tenants t    ON t.id = j.client_id
             JOIN units u      ON u.id = j.tradesman_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             LEFT JOIN job_reminder_rules jrr
                    ON jrr.account_id = acc.id AND jrr.stage = jr.stage AND jrr.is_active = 1
             WHERE jr.status = 'pending'
               AND jr.scheduled_at <= NOW()
               AND j.status = 'scheduled'
               AND t.reminders_paused = 0
               AND acc.subscription_status IN ('trialing', 'active')
               AND (acc.subscription_status != 'trialing' OR acc.trial_ends_at > NOW())"
        );

        $sent = 0;
        foreach ($due as $r) {
            $success = self::dispatch($r);
            $status  = $success ? 'sent' : 'failed';

            DB::execute(
                'UPDATE job_reminders SET status=?, sent_at=NOW() WHERE id=?',
                [$status, $r['id']]
            );

            if ($success) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function sendConfirmation(int $jobId, int $accountId): bool
    {
        $row = DB::fetchOne(
            "SELECT j.scheduled_at, j.job_type, j.estimated_cost_cents, j.address,
                    t.full_name   as client_name,
                    t.email       as client_email,
                    t.phone       as client_phone,
                    t.preferred_channel,
                    u.unit_label  as tradesman_name,
                    p.name        as company_name,
                    acc.name      as business_name,
                    acc.payment_link,
                    acc.contact_phone,
                    jrr.subject   as tpl_subject,
                    jrr.body      as tpl_body
             FROM jobs j
             JOIN tenants t    ON t.id = j.client_id
             JOIN units u      ON u.id = j.tradesman_id
             JOIN properties p ON p.id = u.property_id
             JOIN accounts acc ON acc.id = p.account_id
             LEFT JOIN job_reminder_rules jrr
                    ON jrr.account_id = acc.id AND jrr.stage = 'confirmation' AND jrr.is_active = 1
             WHERE j.id = ? AND j.account_id = ?",
            [$jobId, $accountId]
        );

        if (!$row) {
            return false;
        }

        return self::dispatch($row);
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
        $costFormatted = '$' . number_format(($r['estimated_cost_cents'] ?? 0) / 100, 2);
        $whenFormatted = date('l, F j \a\t g:i A', strtotime($r['scheduled_at']));

        $vars = [
            '{client_name}'    => $r['client_name'],
            '{job_type}'       => $r['job_type'],
            '{scheduled_at}'   => $whenFormatted,
            '{address}'        => $r['address'] ?? '',
            '{tradesman_name}' => $r['tradesman_name'],
            '{company_name}'   => $r['company_name'],
            '{estimated_cost}' => $costFormatted,
            '{business_name}'  => $r['business_name'],
            '{payment_link}'   => $r['payment_link'] ? 'Pay online here: ' . $r['payment_link'] : '',
            '{contact_phone}'  => $r['contact_phone'] ? 'For more information call ' . $r['contact_phone'] : '',
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
