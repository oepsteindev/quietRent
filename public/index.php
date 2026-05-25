<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Autoloader.php';
require BASE_PATH . '/vendor/autoload.php';

use QuietRent\Core\{Autoloader, Env, Auth, Router};
use QuietRent\Controllers\{
    AuthController,
    DashboardController,
    PropertyController,
    UnitController,
    TenantController,
    RentController,
    LeaseController,
    ImportController,
    ReminderRuleController,
    BillingController,
    AppointmentController,
    AppointmentReminderRuleController,
    AccountSettingsController,
    AccountController,
    JobController,
    JobReminderRuleController,
    InvoiceController,
    UnsubscribeController,
};

Autoloader::register(BASE_PATH);
Env::load(BASE_PATH . '/.env');
Auth::start();

// ----------------------------------------------------------------
// Detect if this is an API request or a page request
// API routes return JSON; all other routes return the SPA shell
// ----------------------------------------------------------------

$router = new Router();

// ── Auth routes ──────────────────────────────────────────────────
$router->get('/unsubscribe',    [UnsubscribeController::class, 'handle']);

// ── Auth routes ──────────────────────────────────────────────────
$router->get('/login',          [AuthController::class, 'showLogin']);
$router->post('/login',         [AuthController::class, 'login']);
$router->get('/register',       [AuthController::class, 'showRegister']);
$router->post('/register',      [AuthController::class, 'register']);
$router->post('/logout',        [AuthController::class, 'logout']);
$router->get('/forgot-password',[AuthController::class, 'showForgot']);
$router->post('/forgot-password',[AuthController::class, 'forgot']);
$router->get('/reset-password', [AuthController::class, 'showReset']);
$router->post('/reset-password',[AuthController::class, 'reset']);

// ── SPA page routes (all serve the Vue shell) ────────────────────
foreach (['/dashboard','/properties','/properties/{id}','/tenants','/tenants/{id}',
          '/units','/rent','/appointments','/appointment-payments','/jobs','/invoices','/invoices/{id}','/settings','/import','/billing','/'] as $route) {
    $router->get($route, [DashboardController::class, 'index']);
}

// ── API routes ───────────────────────────────────────────────────
$router->get('/api/csrf',                   function() {
    \QuietRent\Core\Response::json(['csrf' => Auth::csrf()]);
});

$router->get('/api/dashboard',              [DashboardController::class, 'data']);

$router->get('/api/properties',             [PropertyController::class, 'index']);
$router->post('/api/properties',            [PropertyController::class, 'store']);
$router->get('/api/properties/{id}',        [PropertyController::class, 'show']);
$router->post('/api/properties/{id}',       [PropertyController::class, 'update']);  // _method=PUT
$router->post('/api/properties/{id}/delete',[PropertyController::class, 'destroy']);

$router->get('/api/units',                  [UnitController::class, 'index']);
$router->post('/api/units',                 [UnitController::class, 'store']);
$router->post('/api/units/{id}',            [UnitController::class, 'update']);
$router->post('/api/units/{id}/delete',     [UnitController::class, 'destroy']);

$router->get('/api/tenants',                [TenantController::class, 'index']);
$router->post('/api/tenants',               [TenantController::class, 'store']);
$router->get('/api/tenants/{id}',           [TenantController::class, 'show']);
$router->post('/api/tenants/{id}',          [TenantController::class, 'update']);
$router->post('/api/tenants/{id}/delete',   [TenantController::class, 'destroy']);
$router->post('/api/tenants/{id}/pause',    [TenantController::class, 'togglePause']);

$router->get('/api/rent',                   [RentController::class, 'index']);
$router->post('/api/rent/{id}/paid',        [RentController::class, 'markPaid']);
$router->post('/api/rent/{id}/waive',       [RentController::class, 'waive']);

$router->get('/api/leases/tenant/{id}',     [LeaseController::class, 'forTenant']);
$router->post('/api/leases',                [LeaseController::class, 'store']);
$router->post('/api/leases/{id}/end',       [LeaseController::class, 'end']);

$router->post('/api/import',                [ImportController::class, 'store']);

$router->get('/api/reminder-rules',         [ReminderRuleController::class, 'index']);
$router->post('/api/reminder-rules/{id}',   [ReminderRuleController::class, 'update']);

$router->post('/api/billing/checkout',      [BillingController::class, 'checkout']);
$router->post('/api/webhooks/stripe',        [BillingController::class, 'webhook']);
$router->get('/api/billing/status',          [BillingController::class, 'status']);
$router->post('/api/billing/cancel',         [BillingController::class, 'cancel']);
$router->get('/api/billing/invoices',        [BillingController::class, 'invoices']);
$router->post('/api/billing/change-plan',    [BillingController::class, 'changePlan']);

// ── Appointment routes (hairdresser vertical) ─────────────────────
$router->get('/api/appointments',                     [AppointmentController::class, 'index']);
$router->post('/api/appointments',                    [AppointmentController::class, 'store']);
$router->get('/api/appointments/{id}',                [AppointmentController::class, 'show']);
$router->post('/api/appointments/{id}',               [AppointmentController::class, 'update']);
$router->post('/api/appointments/{id}/cancel',        [AppointmentController::class, 'cancel']);
$router->post('/api/appointments/{id}/complete',      [AppointmentController::class, 'complete']);
$router->post('/api/appointments/{id}/no-show',       [AppointmentController::class, 'noShow']);

$router->get('/api/appointment-payments',             [AppointmentController::class, 'payments']);
$router->post('/api/appointment-payments/{id}/paid',  [AppointmentController::class, 'markPaid']);
$router->post('/api/appointment-payments/{id}/waive', [AppointmentController::class, 'waiveFee']);

$router->get('/api/appointment-reminder-rules',       [AppointmentReminderRuleController::class, 'index']);
$router->post('/api/appointment-reminder-rules/{id}', [AppointmentReminderRuleController::class, 'update']);

// ── Job routes (tradesmen vertical) ──────────────────────────────
$router->get('/api/jobs',                   [JobController::class, 'index']);
$router->post('/api/jobs',                  [JobController::class, 'store']);
$router->get('/api/jobs/{id}',              [JobController::class, 'show']);
$router->post('/api/jobs/{id}',             [JobController::class, 'update']);
$router->post('/api/jobs/{id}/cancel',      [JobController::class, 'cancel']);
$router->post('/api/jobs/{id}/complete',    [JobController::class, 'complete']);
$router->post('/api/jobs/{id}/no-show',     [JobController::class, 'noShow']);

$router->get('/api/job-reminder-rules',     [JobReminderRuleController::class, 'index']);
$router->post('/api/job-reminder-rules/{id}', [JobReminderRuleController::class, 'update']);

// ── Invoice routes (tradesmen vertical) ──────────────────────────
$router->get('/api/invoices',                  [InvoiceController::class, 'index']);
$router->post('/api/invoices',                 [InvoiceController::class, 'store']);
$router->get('/api/invoices/{id}',             [InvoiceController::class, 'show']);
$router->post('/api/invoices/{id}',            [InvoiceController::class, 'update']);
$router->post('/api/invoices/{id}/send',       [InvoiceController::class, 'send']);
$router->post('/api/invoices/{id}/mark-paid',  [InvoiceController::class, 'markPaid']);
$router->post('/api/invoices/{id}/delete',     [InvoiceController::class, 'destroy']);
$router->get('/api/invoices/{id}/download',    [InvoiceController::class, 'download']);

$router->get('/api/account-settings',                 [AccountSettingsController::class, 'show']);
$router->post('/api/account-settings',                [AccountSettingsController::class, 'update']);

$router->get('/api/accounts',                         [AccountController::class, 'index']);
$router->post('/api/accounts',                        [AccountController::class, 'store']);
$router->post('/api/switch-account',                  [AccountController::class, 'switchAccount']);
$router->post('/api/switch-to-vertical',              [AccountController::class, 'switchToVertical']);

try {
    $router->dispatch();
} catch (Throwable $e) {
    $isApi = strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    if ($isApi) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    } else {
        // Log the error for non-API requests
        error_log($e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
        require __DIR__ . '/shell.php';
    }
}
