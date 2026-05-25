<?php
/**
 * Public cron endpoint for external services like cron-job.org
 *
 * Call this URL hourly from an external cron service:
 * https://getquietnotify.com/cron.php
 *
 * This endpoint runs the core cron job logic without requiring
 * shell access on the hosting provider.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Autoloader.php';
require BASE_PATH . '/vendor/autoload.php';

use QuietRent\Core\{Autoloader, Env};
use QuietRent\Services\{BillingEngine, ReminderDispatcher, AppointmentReminderDispatcher};
use QuietRent\Models\RentCharge;

try {
    Autoloader::register(BASE_PATH);
    Env::load(BASE_PATH . '/.env');

    RentCharge::updateStatuses();
    BillingEngine::generateMonth();
    BillingEngine::applyLateFees();
    ReminderDispatcher::scheduleAll();
    ReminderDispatcher::suppressPaid();
    $sent = ReminderDispatcher::sendDue();

    AppointmentReminderDispatcher::scheduleAll();
    AppointmentReminderDispatcher::suppressCanceled();
    $apptSent = AppointmentReminderDispatcher::sendDue();

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'sent'      => $sent,
        'appt_sent' => $apptSent,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $e->getMessage()
    ]);
    error_log('Cron endpoint error: ' . $e->getMessage());
}
