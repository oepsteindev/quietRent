<?php

/**
 * Cold Outreach Mailer
 *
 * Reads a lead CSV and sends personalized cold emails via SMTP.
 * Tracks sent addresses per-audience in a log file to avoid duplicates on re-runs.
 *
 * Usage:
 *   php tools/send_outreach.php --audience=landlord
 *   php tools/send_outreach.php --audience=hairdresser
 *   php tools/send_outreach.php --audience=tradesperson
 *   php tools/send_outreach.php --audience=landlord --dry-run
 *   php tools/send_outreach.php --audience=landlord --test=you@example.com
 *   php tools/send_outreach.php --csv=path/to/custom.csv --audience=landlord
 *
 * CSV must have columns: company, emails
 * Placeholder emails (user@domain.com) are automatically skipped.
 *
 * Audiences and their default CSVs:
 *   landlord     → tools/landlord_leads.csv
 *   hairdresser  → tools/hairdresser_leads.csv
 *   tradesperson → tools/tradespeople_leads.csv
 *   (no audience / custom --csv) → tools/tampa_leads.csv
 *
 * Sent logs: tools/outreach_sent_{audience}.log
 */

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Parse args ─────────────────────────────────────────────────────────────
$dryRun   = in_array('--dry-run', $argv);
$testTo   = null;
$audience = null;
$csvFile  = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--test='))     $testTo   = substr($arg, 7);
    if (str_starts_with($arg, '--csv='))      $csvFile  = substr($arg, 6);
    if (str_starts_with($arg, '--audience=')) $audience = substr($arg, 11);
}

$audienceDefaults = [
    'landlord'     => __DIR__ . '/landlord_leads.csv',
    'hairdresser'  => __DIR__ . '/hairdresser_leads.csv',
    'tradesperson' => __DIR__ . '/tradespeople_leads.csv',
];

if ($csvFile === null) {
    $csvFile = $audienceDefaults[$audience] ?? (__DIR__ . '/tampa_leads.csv');
}

$logSuffix = $audience ? "_{$audience}" : '';
$sentLog   = __DIR__ . "/outreach_sent{$logSuffix}.log";

// ── Load .env ──────────────────────────────────────────────────────────────
if (file_exists('.env')) {
    foreach (file('.env') as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}

// ── Config ─────────────────────────────────────────────────────────────────
$mailCfg = [
    'host'     => $_ENV['MAIL_HOST']      ?? '',
    'port'     => (int)($_ENV['MAIL_PORT'] ?? 465),
    'user'     => $_ENV['MAIL_USERNAME']  ?? '',
    'pass'     => $_ENV['MAIL_PASSWORD']  ?? '',
    'from'     => $_ENV['MAIL_FROM']      ?? '',
    'fromName' => $_ENV['MAIL_FROM_NAME'] ?? 'Oren at QuietNotify',
];

$appUrl          = rtrim($_ENV['APP_URL'] ?? 'https://getquietnotify.com', '/');
$unsubSecret     = $_ENV['UNSUBSCRIBE_SECRET'] ?? '';
$unsubFile       = __DIR__ . '/unsubscribed.txt';
$globalUnsubs    = file_exists($unsubFile)
    ? array_flip(array_map('strtolower', array_map('trim', file($unsubFile))))
    : [];

// ── Email templates per audience ───────────────────────────────────────────
$templates = [

    'landlord' => [
        'subject' => 'Your tenants will never forget rent is due',
        'text'    => <<<TEXT
Hi,

Most landlords I talk to are doing the same thing every single month - manually texting or calling tenants a few days before rent is due, then following up again when it's late, then again after that. It's exhausting, and it shouldn't be your job.

I built QuietNotify to take that off your plate completely.

You enter your tenants and due dates once. From that point on, QuietNotify automatically sends them a reminder a few days before rent is due, again on the due date, and a late notice if they miss it. You choose whether they get an email, a text, or both - whatever actually gets their attention. You never think about it again. You just check who paid.

No more awkward "just a reminder" texts. No more chasing. It runs in the background and handles it for you.

Free trial at https://getquietnotify.com?utm_source=outreach&utm_campaign=landlord - takes about 10 minutes to set up.

- Oren

---
You're receiving this because your business appeared in a local search.
Unsubscribe: Reply "unsubscribe" to opt out.
TEXT,
    ],

    'hairdresser' => [
        'subject' => 'Empty chairs cost you money — this fixes it',
        'text'    => <<<TEXT
Hi,

An empty chair is the worst thing in a salon. And most of the time it's not because the client cancelled — it's because they forgot, and nobody reminded them.

I built QuietNotify specifically for salons and stylists to fix that.

You book the appointment like you normally would. QuietNotify automatically texts or emails your client the day before and the morning of — so they show up, they're on time, and your chair stays filled. You don't touch a thing. It just runs in the background.

It also brings clients back. Regulars who haven't booked in a while get a nudge. New clients get a follow-up. Your calendar fills itself and you spend your time doing what you're actually good at — not chasing bookings.

Free trial at https://getquietnotify.com?utm_source=outreach&utm_campaign=hairdresser - takes about 5 minutes to set up.

- Oren

---
You're receiving this because your business appeared in a local search.
Unsubscribe: Reply "unsubscribe" to opt out.
TEXT,
    ],

    'tradesperson' => [
        'subject' => 'Never show up to a job where nobody is home',
        'text'    => <<<TEXT
Hi,

If you've ever driven 45 minutes to a job and nobody answered the door, you know how much that stings. Wasted time, wasted gas, a customer who "totally forgot" - and now you have to reschedule and do it all over again.

I built QuietNotify to make that stop happening.

You schedule the job. QuietNotify automatically contacts your customer the day before and the morning of the appointment - by text, by email, or both - so they're home, they're ready, and the job goes smoothly. No more calling to confirm. No more hoping they remembered. It's handled automatically.

It also keeps your whole customer base organized. Nothing falls through the cracks, and you always have a clear picture of what's coming up.

Free trial at https://getquietnotify.com?utm_source=outreach&utm_campaign=tradesperson - setup takes about 5 minutes.

- Oren

---
You're receiving this because your business appeared in a local search.
Unsubscribe: Reply "unsubscribe" to opt out.
TEXT,
    ],

    // Fallback / generic
    'default' => [
        'subject' => 'Your customers will stop slipping through the cracks',
        'text'    => <<<TEXT
Hi,

Keeping up with your customer base is one of those things that's easy to let slide - and before you know it, people who should have come back haven't heard from you in months.

I built QuietNotify to handle that automatically. You set up your customers and appointments once, and it reaches out to them by email, text, or both - whichever actually gets their attention. Nothing falls through the cracks, and you stay top of mind without having to manually follow up with everyone.

It runs quietly in the background and keeps your whole customer base engaged - so you can focus on the actual work.

Free trial at https://getquietnotify.com - takes about 10 minutes to get going.

- Oren

---
You're receiving this because your business appeared in a local search.
Unsubscribe: Reply "unsubscribe" to opt out.
TEXT,
    ],
];

$tpl     = $templates[$audience] ?? $templates['default'];
$subject = $tpl['subject'];
$bodyText = trim($tpl['text']);

// ── Load sent log ──────────────────────────────────────────────────────────
$sent = file_exists($sentLog) ? array_flip(array_map('trim', file($sentLog))) : [];

// ── Read CSV ───────────────────────────────────────────────────────────────
if (!file_exists($csvFile)) {
    fwrite(STDERR, "ERROR: CSV not found: {$csvFile}\n");
    exit(1);
}

$fh     = fopen($csvFile, 'r');
$header = array_map('trim', fgetcsv($fh, 0, ',', '"', '\\'));

$leads = [];
while ($row = fgetcsv($fh, 0, ',', '"', '\\')) {
    $r = array_combine($header, $row);
    if (empty($r['emails'])) continue;

    $emailList = array_filter(array_map('trim', explode(',', $r['emails'])));
    foreach ($emailList as $email) {
        $email = strtolower(urldecode($email));
        $email = trim($email);
        // Skip placeholders and invalid addresses
        if (str_contains($email, 'user@domain')) continue;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        // Skip known non-human / placeholder domains
        $blockedDomains = ['sentry.io', 'wixpress.com', 'example.com', 'test.com', 'mailinator.com', 'godaddy.com', 'secureserver.net'];
        $blockedPrefixes = ['filler@', 'noreply@', 'no-reply@', 'donotreply@', 'webmaster@'];
        $domain = substr($email, strrpos($email, '@') + 1);
        $blocked = false;
        foreach ($blockedDomains as $bd) {
            if ($domain === $bd || str_ends_with($domain, '.' . $bd)) { $blocked = true; break; }
        }
        if (!$blocked) {
            foreach ($blockedPrefixes as $bp) {
                if (str_starts_with($email, $bp)) { $blocked = true; break; }
            }
        }
        if ($blocked) continue;

        $leads[] = [
            'email'   => $email,
            'company' => $r['company'] ?? '',
        ];
    }
}
fclose($fh);

// Dedupe by email address
$seen    = [];
$deduped = [];
foreach ($leads as $lead) {
    $key = strtolower($lead['email']);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $deduped[]  = $lead;
    }
}
$removed = count($leads) - count($deduped);
$leads   = $deduped;

$audienceLabel = $audience ? " [{$audience}]" : '';
echo ($dryRun ? "[DRY RUN]" : "[LIVE]") . "{$audienceLabel} {$csvFile}\n";
echo "Found " . count($leads) . " valid unique addresses";
if ($removed > 0) echo " ({$removed} duplicates removed)";
echo ".\n";
if ($testTo) echo "TEST MODE: all emails → {$testTo}\n";
echo str_repeat('-', 60) . "\n";

// ── Send ───────────────────────────────────────────────────────────────────
$countSent = 0;
$countSkip = 0;

foreach ($leads as $lead) {
    $to = $testTo ?: $lead['email'];

    if (!$testTo && isset($globalUnsubs[strtolower($lead['email'])])) {
        echo "  SKIP (unsubscribed): {$lead['email']}\n";
        $countSkip++;
        continue;
    }

    if (!$testTo && isset($sent[$lead['email']])) {
        echo "  SKIP (already sent): {$lead['email']}\n";
        $countSkip++;
        continue;
    }

    if ($dryRun) {
        echo "  [DRY] → {$to}  ({$lead['company']})\n";
        $countSent++;
        continue;
    }

    // Build a signed unsubscribe link for this recipient
    $unsubEmail = $testTo ? $lead['email'] : $to;
    $unsubToken = hash_hmac('sha256', strtolower($unsubEmail), $unsubSecret);
    $unsubLink  = $appUrl . '/unsubscribe?email=' . rawurlencode($unsubEmail) . '&token=' . $unsubToken;
    $personalBody = str_replace(
        'Reply "unsubscribe" to opt out.',
        'Unsubscribe: ' . $unsubLink,
        $bodyText
    );

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailCfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailCfg['user'];
        $mail->Password   = $mailCfg['pass'];
        $mail->SMTPSecure = $mailCfg['port'] === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mailCfg['port'];

        $mail->CharSet = 'UTF-8';

        $mail->setFrom($mailCfg['from'], $mailCfg['fromName']);
        $mail->addAddress($to);
        $mail->addReplyTo($mailCfg['from'], $mailCfg['fromName']);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $personalBody;

        $mail->send();

        echo "  SENT: {$to}\n";

        if (!$testTo) {
            file_put_contents($sentLog, $lead['email'] . "\n", FILE_APPEND);
            $sent[$lead['email']] = true;
        }

        $countSent++;
        sleep(2); // ~30/min — well under hosting SMTP limits
    } catch (Exception $e) {
        echo "  FAIL: {$to} — {$mail->ErrorInfo}\n";
    }
}

echo str_repeat('-', 60) . "\n";
echo "Sent: {$countSent}  |  Skipped: {$countSkip}\n";
if (!$dryRun && !$testTo) echo "Sent log: {$sentLog}\n";
