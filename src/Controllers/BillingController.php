<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response, Env, DB};
use QuietRent\Models\Account;
use QuietRent\Services\Mailer;
use Stripe\StripeClient;
use Stripe\Webhook;

class BillingController
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secretKey = Env::get('STRIPE_SECRET_KEY');
        if (!$secretKey) {
            throw new \Exception('STRIPE_SECRET_KEY not configured');
        }
        $this->stripe = new StripeClient(['api_key' => $secretKey]);
    }

    public function checkout(array $params): void
    {
        Auth::require();
        $accountId = Auth::accountId();
        $account = Account::find($accountId);

        if (!$account) {
            http_response_code(404);
            Response::json(['error' => 'Account not found']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $plan = $data['plan'] ?? null;

        if (!in_array($plan, ['starter', 'pro'], true)) {
            http_response_code(400);
            Response::json(['error' => 'Invalid plan']);
            return;
        }

        $prices = [
            'starter' => 1900, // $19/month in cents
            'pro'     => 4900, // $49/month in cents
        ];

        try {
            // Create or get Stripe customer
            $customerId = $account['stripe_customer_id'];
            if (!$customerId) {
                $customer = $this->stripe->customers->create([
                    'metadata' => ['account_id' => $accountId],
                ]);
                $customerId = $customer->id;
                Account::setStripeCustomer($accountId, $customerId);
            }

            $host = $_SERVER['HTTP_HOST'] ?? parse_url(Env::get('APP_URL', 'http://localhost:8080'), PHP_URL_HOST);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $appUrl = $scheme . '://' . $host;

            // Create checkout session with metadata to track plan
            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => ucfirst($plan) . ' Plan',
                            ],
                            'unit_amount' => $prices[$plan],
                            'recurring' => [
                                'interval' => 'month',
                                'interval_count' => 1,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'metadata' => [
                    'plan' => $plan,
                    'account_id' => (string) $accountId,
                ],
                'success_url' => $appUrl . '/billing?success=true&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $appUrl . '/billing?canceled=true',
            ]);

            Response::json(['url' => $session->url]);
        } catch (\Exception $e) {
            http_response_code(500);
            Response::json(['error' => 'Failed to create checkout session: ' . $e->getMessage()]);
        }
    }

    public function verifySession(array $params): void
    {
        $sessionId = $_GET['session_id'] ?? '';
        if (!$sessionId) {
            http_response_code(400);
            Response::json(['error' => 'Missing session_id']);
            return;
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);
            if ($session->payment_status === 'paid' || $session->status === 'complete') {
                $this->handleCheckoutCompleted($session);
            }
            Response::json(['ok' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            Response::json(['error' => $e->getMessage()]);
        }
    }

    public function webhook(array $params): void
    {
        $payload = file_get_contents('php://input');
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = Env::get('STRIPE_WEBHOOK_SECRET');

        if (!$secret) {
            http_response_code(501);
            Response::json(['error' => 'Webhook secret not configured']);
            return;
        }

        try {
            $event = Webhook::constructEvent($payload, $sig, $secret);
        } catch (\UnexpectedValueException $e) {
            http_response_code(400);
            Response::json(['error' => 'Invalid payload']);
            return;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(403);
            Response::json(['error' => 'Invalid signature']);
            return;
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
        }

        Response::json(['received' => true]);
    }

    private function handleCheckoutCompleted($session): void
    {
        try {
            $customerId = $session->customer;
            $subscriptionId = $session->subscription;

            if (!$customerId || !$subscriptionId) {
                return;
            }

            $plan       = $session->metadata['plan'] ?? null;
            $accountId  = isset($session->metadata['account_id']) ? (int) $session->metadata['account_id'] : null;

            if (!$accountId) {
                return;
            }

            // Get user email for confirmation email
            $account = DB::fetchOne(
                'SELECT u.email FROM users u
                 LEFT JOIN user_accounts ua ON ua.user_id = u.id
                 WHERE u.account_id = ? OR ua.account_id = ?
                 LIMIT 1',
                [$accountId, $accountId]
            );

            // If plan not in metadata, try to extract from subscription
            if (!$plan) {
                $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
                $plan = $this->getPlanFromPrice($subscription->items->data[0]->price->id);
            }

            // Update account with subscription info
            Account::setSubscription($accountId, $subscriptionId, $plan, 'active');

            // Send confirmation email
            $prices = ['starter' => '$19/month', 'pro' => '$49/month'];
            $price = $prices[$plan] ?? 'custom';
            $subject = 'Subscription confirmed — Quiet Rent';
            $body = "Hi,\n\n"
                  . "Your subscription to the {$plan} plan ({$price}) has been confirmed.\n\n"
                  . "Your subscription is now active and reminders will continue running.\n\n"
                  . "You can manage your subscription anytime at: " . Env::get('APP_URL') . "/billing\n\n"
                  . "Thank you!\n"
                  . "Quiet Rent";
            $emailSent = Mailer::send($account['email'], $subject, $body);
            error_log('Email send to ' . $account['email'] . ': ' . ($emailSent ? 'SUCCESS' : 'FAILED'));
        } catch (\Exception $e) {
            error_log('Error handling checkout completed: ' . $e->getMessage());
        }
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        try {
            $customerId = $subscription->customer;
            $account = DB::fetchOne(
                'SELECT id FROM accounts WHERE stripe_customer_id = ?',
                [$customerId]
            );

            if (!$account) {
                return;
            }

            $status = $subscription->status;
            Account::updateSubscriptionStatus($account['id'], $status);
        } catch (\Exception $e) {
            error_log('Error handling subscription updated: ' . $e->getMessage());
        }
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        try {
            $customerId = $subscription->customer;
            $account = DB::fetchOne(
                'SELECT id FROM accounts WHERE stripe_customer_id = ?',
                [$customerId]
            );

            if (!$account) {
                return;
            }

            Account::updateSubscriptionStatus($account['id'], 'canceled');
        } catch (\Exception $e) {
            error_log('Error handling subscription deleted: ' . $e->getMessage());
        }
    }

    private function getPlanFromPrice($priceId): string
    {
        try {
            $price = $this->stripe->prices->retrieve($priceId);
            // Return plan name based on amount (in cents)
            if ($price->unit_amount === 1900) {
                return 'starter';
            } elseif ($price->unit_amount === 4900) {
                return 'pro';
            }
            return 'trial';
        } catch (\Exception $e) {
            return 'trial';
        }
    }

    public function status(array $params): void
    {
        Auth::require();
        $accountId = Auth::accountId();
        $account = Account::find($accountId);

        if (!$account) {
            http_response_code(404);
            Response::json(['error' => 'Account not found']);
            return;
        }

        $response = [
            'plan' => $account['plan'],
            'status' => $account['subscription_status'],
            'trial_ends_at' => $account['trial_ends_at'],
            'subscription_ends_at' => $account['subscription_ends_at'],
            'cancel_at_period_end' => false,
            'current_period_end' => null,
        ];

        if ($account['stripe_subscription_id']) {
            try {
                $subscription = $this->stripe->subscriptions->retrieve($account['stripe_subscription_id']);
                $response['cancel_at_period_end'] = $subscription->cancel_at_period_end ? true : false;
                $periodEnd = $subscription->items->data[0]->current_period_end ?? null;
                $response['current_period_end'] = $periodEnd ? date('Y-m-d', $periodEnd) : null;
            } catch (\Exception $e) {
                error_log('Error fetching subscription: ' . $e->getMessage());
            }
        }

        Response::json($response);
    }

    public function cancel(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $account = Account::find($accountId);

        if (!$account) {
            http_response_code(404);
            Response::json(['error' => 'Account not found']);
            return;
        }

        if (!$account['stripe_subscription_id']) {
            http_response_code(400);
            Response::json(['error' => 'No active subscription']);
            return;
        }

        try {
            $subscription = $this->stripe->subscriptions->update(
                $account['stripe_subscription_id'],
                ['cancel_at_period_end' => true]
            );

            // Store the end date
            if ($subscription->cancel_at) {
                $endsAt = date('Y-m-d H:i:s', $subscription->cancel_at);
                Account::setPlanEndDate($accountId, $endsAt);
            }

            $periodEnd = $subscription->items->data[0]->current_period_end ?? $subscription->cancel_at ?? null;
            Response::json(['ok' => true, 'ends_at' => $periodEnd ? date('Y-m-d', $periodEnd) : null]);
        } catch (\Exception $e) {
            http_response_code(500);
            Response::json(['error' => 'Failed to cancel subscription: ' . $e->getMessage()]);
        }
    }

    public function invoices(array $params): void
    {
        Auth::require();
        $accountId = Auth::accountId();
        $account = Account::find($accountId);

        if (!$account) {
            http_response_code(404);
            Response::json(['error' => 'Account not found']);
            return;
        }

        if (!$account['stripe_customer_id']) {
            Response::json([]);
            return;
        }

        try {
            $invoices = $this->stripe->invoices->all([
                'customer' => $account['stripe_customer_id'],
                'limit' => 24,
            ]);

            $formatted = [];
            foreach ($invoices->data as $invoice) {
                $formatted[] = [
                    'id' => $invoice->id,
                    'date' => date('Y-m-d', $invoice->created),
                    'amount' => number_format($invoice->total / 100, 2),
                    'status' => $invoice->status,
                    'pdf_url' => $invoice->invoice_pdf,
                ];
            }

            Response::json($formatted);
        } catch (\Exception $e) {
            http_response_code(500);
            Response::json(['error' => 'Failed to fetch invoices: ' . $e->getMessage()]);
        }
    }

    public function changePlan(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $accountId = Auth::accountId();
        $data = json_decode(file_get_contents('php://input'), true);
        $newPlan = $data['plan'] ?? null;

        if (!in_array($newPlan, ['starter', 'pro'], true)) {
            http_response_code(400);
            Response::json(['error' => 'Invalid plan']);
            return;
        }

        $account = Account::find($accountId);
        if (!$account) {
            http_response_code(404);
            Response::json(['error' => 'Account not found']);
            return;
        }

        if (!$account['stripe_subscription_id']) {
            http_response_code(400);
            Response::json(['error' => 'No active subscription']);
            return;
        }

        try {
            $subscription = $this->stripe->subscriptions->retrieve($account['stripe_subscription_id']);
            $itemId = $subscription->items->data[0]->id;

            $prices = ['starter' => 1900, 'pro' => 4900];

            // Inline checkout products get archived by Stripe after use, so create a
            // fresh product for the new plan to guarantee we reference an active one.
            $product = $this->stripe->products->create([
                'name' => ucfirst($newPlan) . ' Plan',
            ]);

            $this->stripe->subscriptions->update(
                $account['stripe_subscription_id'],
                [
                    'items' => [[
                        'id' => $itemId,
                        'price_data' => [
                            'currency' => 'usd',
                            'product'  => $product->id,
                            'unit_amount' => $prices[$newPlan],
                            'recurring' => ['interval' => 'month'],
                        ],
                    ]],
                    'proration_behavior' => 'create_prorations',
                ]
            );

            Account::setSubscription($accountId, $account['stripe_subscription_id'], $newPlan, 'active');
            Response::json(['ok' => true, 'plan' => $newPlan]);
        } catch (\Exception $e) {
            http_response_code(500);
            Response::json(['error' => 'Failed to change plan: ' . $e->getMessage()]);
        }
    }
}
