<?php

namespace QuietRent\Services;

use QuietRent\Core\{DB, Env};

class TrialNotifier
{
    /**
     * Send a 24-hour trial expiry warning to accounts whose trial ends within the next 24 hours.
     * Runs every minute via cron — trial_warned_at prevents duplicate sends.
     */
    public static function warnExpiring(): int
    {
        $expiring = DB::fetchAll(
            "SELECT a.id, a.name, a.product_type, a.trial_ends_at, u.email
             FROM accounts a
             JOIN users u ON u.account_id = a.id
             WHERE a.plan = 'trial'
               AND a.subscription_status = 'trialing'
               AND a.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
               AND a.trial_warned_at IS NULL",
            []
        );

        $sent = 0;
        foreach ($expiring as $row) {
            $billingUrl = self::billingUrl($row['product_type']);
            $endsAt     = date('F j, Y \a\t g:i A', strtotime($row['trial_ends_at']));

            $subject = 'Your free trial expires in 24 hours';
            $body    = "Hi,\n\n"
                     . "Your free trial for {$row['name']} expires on {$endsAt}.\n\n"
                     . "After that, reminders will stop sending until you subscribe.\n\n"
                     . "Subscribe now to keep things running:\n"
                     . $billingUrl . "\n\n"
                     . "Starter plan is \$19/month — cancel anytime.\n\n"
                     . "QuietNotify";

            $ok = Mailer::send($row['email'], $subject, $body);

            if ($ok) {
                DB::execute(
                    'UPDATE accounts SET trial_warned_at = NOW() WHERE id = ?',
                    [$row['id']]
                );
                $sent++;
            }
        }

        return $sent;
    }

    private static function billingUrl(string $productType): string
    {
        $subdomains = [
            'landlords'   => 'landlords',
            'hairdressers'=> 'hairdressers',
            'tradesmen'   => 'tradesmen',
        ];
        $sub = $subdomains[$productType] ?? 'landlords';
        return "https://{$sub}.getquietnotify.com/billing";
    }
}
