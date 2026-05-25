<?php

/**
 * Quiet Rent – cron runner
 *
 * Run via cron every minute:
 *   * * * * * php /path/to/quietRent/cron/run.php >> /dev/null 2>&1
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Autoloader.php';
require BASE_PATH . '/vendor/autoload.php';

use QuietRent\Core\{Autoloader, Env, DB};
use QuietRent\Models\RentCharge;
use QuietRent\Services\{BillingEngine, ReminderDispatcher, AppointmentReminderDispatcher, JobReminderDispatcher};

Autoloader::register(BASE_PATH);
Env::load(BASE_PATH . '/.env');

$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

// 1. Update charge statuses (upcoming → due → late)
RentCharge::updateStatuses();
$log('Statuses updated');

// 2. Generate this month's charges (idempotent)
$created = BillingEngine::generateMonth();
$log("Rent charges created: $created");

// 3. Apply late fees
$fees = BillingEngine::applyLateFees();
$log("Late fees applied: $fees");

// 4. Schedule reminders for new charges
$scheduled = ReminderDispatcher::scheduleAll();
$log("Reminders scheduled: $scheduled");

// 5. Suppress reminders for paid charges
$suppressed = ReminderDispatcher::suppressPaid();
$log("Reminders suppressed: $suppressed");

// 6. Send due reminders
$sent = ReminderDispatcher::sendDue();
$log("Reminders sent: $sent");

// 7. Appointment reminders
$scheduled = AppointmentReminderDispatcher::scheduleAll();
$log("Appointment reminders scheduled: $scheduled");
$suppressed = AppointmentReminderDispatcher::suppressCanceled();
$log("Appointment reminders suppressed: $suppressed");
$apptSent = AppointmentReminderDispatcher::sendDue();
$log("Appointment reminders sent: $apptSent");

// 8. Job reminders (tradesmen vertical)
$scheduled = JobReminderDispatcher::scheduleAll();
$log("Job reminders scheduled: $scheduled");
$suppressed = JobReminderDispatcher::suppressCanceled();
$log("Job reminders suppressed: $suppressed");
$jobSent = JobReminderDispatcher::sendDue();
$log("Job reminders sent: $jobSent");

$log('Cron run complete');
