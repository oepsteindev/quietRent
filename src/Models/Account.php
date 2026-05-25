<?php

namespace QuietRent\Models;

use QuietRent\Core\DB;

class Account
{
    public static function create(string $name, string $productType = 'landlords'): int
    {
        $trialEnds = date('Y-m-d H:i:s', strtotime('+14 days'));
        return DB::insert(
            'INSERT INTO accounts (name, plan, trial_ends_at, subscription_status, product_type) VALUES (?, ?, ?, ?, ?)',
            [$name, 'trial', $trialEnds, 'trialing', $productType]
        );
    }

    public static function find(int $id): array|false
    {
        return DB::fetchOne('SELECT * FROM accounts WHERE id = ?', [$id]);
    }

    public static function isActive(array $account): bool
    {
        if ($account['subscription_status'] === 'active') {
            return true;
        }
        if ($account['plan'] === 'trial' && $account['trial_ends_at'] > date('Y-m-d H:i:s')) {
            return true;
        }
        return false;
    }

    public static function setStripeCustomer(int $id, string $customerId): void
    {
        DB::execute(
            'UPDATE accounts SET stripe_customer_id = ? WHERE id = ?',
            [$customerId, $id]
        );
    }

    public static function setSubscription(int $id, string $subscriptionId, string $plan, string $status): void
    {
        DB::execute(
            'UPDATE accounts SET stripe_subscription_id = ?, plan = ?, subscription_status = ? WHERE id = ?',
            [$subscriptionId, $plan, $status, $id]
        );
    }

    public static function updateSubscriptionStatus(int $id, string $status): void
    {
        DB::execute(
            'UPDATE accounts SET subscription_status = ? WHERE id = ?',
            [$status, $id]
        );
    }

    public static function setPlanEndDate(int $id, ?string $endsAt): void
    {
        DB::execute(
            'UPDATE accounts SET subscription_ends_at = ? WHERE id = ?',
            [$endsAt, $id]
        );
    }

    /** Seed appointment reminder rules for a new hairdresser account */
    public static function seedAppointmentReminderRules(int $accountId): void
    {
        $rules = [
            [
                'stage'   => 'confirmation',
                'subject' => 'Your {service_name} appointment is confirmed',
                'body'    => "Hi {client_name},\n\nYour appointment is confirmed!\n\n  Service: {service_name}\n  When:    {appointment_at}\n  Stylist: {stylist_name}\n  Salon:   {salon_name}\n  Fee:     {fee_amount}\n\n{disclaimer}\n\nSee you soon!\n{business_name}",
            ],
            [
                'stage'   => 'reminder_30h',
                'subject' => 'Reminder: {service_name} at {salon_name}',
                'body'    => "Hi {client_name},\n\nJust a reminder — you have a {service_name} appointment coming up.\n\n  When:    {appointment_at}\n  Stylist: {stylist_name}\n  Salon:   {salon_name}\n  Fee:     {fee_amount}\n\n{disclaimer}\n\n{business_name}",
            ],
            [
                'stage'   => 'reminder_2h',
                'subject' => 'Your {service_name} is in 2 hours',
                'body'    => "Hi {client_name},\n\nHeads up — your {service_name} appointment is in about 2 hours.\n\n  When:    {appointment_at}\n  Stylist: {stylist_name}\n  Salon:   {salon_name}\n\n{disclaimer}\n\nSee you shortly!\n{business_name}",
            ],
        ];

        foreach ($rules as $rule) {
            DB::execute(
                'INSERT IGNORE INTO appointment_reminder_rules (account_id, stage, subject, body)
                 VALUES (?, ?, ?, ?)',
                [$accountId, $rule['stage'], $rule['subject'], $rule['body']]
            );
        }
    }

    /** Seed job reminder rules for a new tradesmen account */
    public static function seedJobReminderRules(int $accountId): void
    {
        $rules = [
            [
                'stage'     => 'confirmation',
                'subject'   => 'Your {job_type} appointment is confirmed',
                'body'      => "Hi {client_name},\n\nYour job is confirmed!\n\n  Service:  {job_type}\n  When:     {scheduled_at}\n  Where:    {address}\n  Tradesman:{tradesman_name}\n  Estimate: {estimated_cost}\n\n{payment_link}\n{contact_phone}\n\nSee you then!\n{business_name}",
                'is_active' => 1,
            ],
            [
                'stage'     => 'reminder_24h',
                'subject'   => 'Reminder: {job_type} tomorrow',
                'body'      => "Hi {client_name},\n\nJust a reminder — you have a job scheduled for tomorrow.\n\n  Service:  {job_type}\n  When:     {scheduled_at}\n  Where:    {address}\n  Tradesman:{tradesman_name}\n  Estimate: {estimated_cost}\n\n{payment_link}\n{contact_phone}\n\n{business_name}",
                'is_active' => 1,
            ],
            [
                'stage'     => 'reminder_2h',
                'subject'   => 'Your {job_type} is in 2 hours',
                'body'      => "Hi {client_name},\n\nYour {job_type} is coming up in about 2 hours.\n\n  When:  {scheduled_at}\n  Where: {address}\n\n{contact_phone}\n\n{business_name}",
                'is_active' => 1,
            ],
            [
                'stage'     => 'completion',
                'subject'   => 'Thanks for choosing {business_name}',
                'body'      => "Hi {client_name},\n\nThanks for having us out today! We hope everything looks great.\n\nIf you have any questions or need follow-up work, don't hesitate to reach out.\n\n{payment_link}\n{contact_phone}\n\n{business_name}",
                'is_active' => 0,
            ],
        ];

        foreach ($rules as $rule) {
            DB::execute(
                'INSERT IGNORE INTO job_reminder_rules (account_id, stage, subject, body, is_active)
                 VALUES (?, ?, ?, ?, ?)',
                [$accountId, $rule['stage'], $rule['subject'], $rule['body'], $rule['is_active']]
            );
        }
    }

    /** Seed default reminder rules for a new account */
    public static function seedReminderRules(int $accountId): void
    {
        $rules = [
            [
                'stage'      => 'pre_due',
                'day_offset' => -3,
                'subject'    => 'Rent reminder for {unit_label}',
                'body'       => "Hi {tenant_name},\n\nJust a friendly reminder that your rent of {rent_amount} for {unit_label} is due on {due_date}.\n\nThanks,\n{landlord_name}",
            ],
            [
                'stage'      => 'due_day',
                'day_offset' => 0,
                'subject'    => 'Rent due today - {unit_label}',
                'body'       => "Hi {tenant_name},\n\nYour rent of {rent_amount} for {unit_label} is due today.\n\nThanks,\n{landlord_name}",
            ],
            [
                'stage'      => 'late_1',
                'day_offset' => 1,
                'subject'    => 'Rent overdue - {unit_label}',
                'body'       => "Hi {tenant_name},\n\nYour rent of {rent_amount} for {unit_label} was due on {due_date} and has not been received.\n\nPlease remit payment as soon as possible.\n\nThanks,\n{landlord_name}",
            ],
            [
                'stage'      => 'late_5',
                'day_offset' => 5,
                'subject'    => 'Urgent: Rent overdue + late fee - {unit_label}',
                'body'       => "Hi {tenant_name},\n\nYour rent of {rent_amount} for {unit_label} is now 5 days overdue. A late fee of {late_fee_amount} has been applied.\n\nTotal due: {total_due}\n\nPlease pay immediately: {payment_link}\n\n{landlord_name}",
            ],
        ];

        foreach ($rules as $rule) {
            DB::execute(
                'INSERT IGNORE INTO reminder_rules (account_id, stage, day_offset, subject, body)
                 VALUES (?, ?, ?, ?, ?)',
                [$accountId, $rule['stage'], $rule['day_offset'], $rule['subject'], $rule['body']]
            );
        }
    }
}
