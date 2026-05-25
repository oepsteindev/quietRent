-- Add per-account settings columns
ALTER TABLE accounts
  ADD COLUMN disclaimer   TEXT         NULL AFTER product_type,
  ADD COLUMN payment_link VARCHAR(500) NULL AFTER disclaimer;

-- Step 1: Widen to include both old and new values
ALTER TABLE appointment_reminder_rules
  MODIFY COLUMN stage ENUM('confirmation','reminder_48h','reminder_30h','reminder_2h') NOT NULL;

-- Step 2: Migrate data
UPDATE appointment_reminder_rules SET stage = 'reminder_30h' WHERE stage = 'reminder_48h';

-- Step 3: Lock down to final set (no more 48h)
ALTER TABLE appointment_reminder_rules
  MODIFY COLUMN stage ENUM('confirmation','reminder_30h','reminder_2h') NOT NULL;

-- Same pattern for appointment_reminders
ALTER TABLE appointment_reminders
  MODIFY COLUMN stage ENUM('reminder_48h','reminder_30h','reminder_2h') NOT NULL;

UPDATE appointment_reminders SET stage = 'reminder_30h' WHERE stage = 'reminder_48h';

ALTER TABLE appointment_reminders
  MODIFY COLUMN stage ENUM('reminder_30h','reminder_2h') NOT NULL;

-- Seed confirmation rules for all hairdresser accounts that don't have one yet
INSERT IGNORE INTO appointment_reminder_rules (account_id, stage, subject, body)
SELECT a.id,
       'confirmation',
       'Your {service_name} appointment is confirmed',
       CONCAT(
         'Hi {client_name},\n\n',
         'Your appointment is confirmed!\n\n',
         '  Service: {service_name}\n',
         '  When:    {appointment_at}\n',
         '  Stylist: {stylist_name}\n',
         '  Salon:   {salon_name}\n',
         '  Fee:     {fee_amount}\n\n',
         '{disclaimer}\n\n',
         'See you soon!\n',
         '{business_name}'
       )
FROM accounts a
WHERE a.product_type = 'hairdressers';

-- Seed 30h rules for hairdresser accounts that still have none (or just 48h now renamed)
-- (reminder_30h rows were already renamed above; just ensure any account missing it gets one)
INSERT IGNORE INTO appointment_reminder_rules (account_id, stage, subject, body)
SELECT a.id,
       'reminder_30h',
       'Reminder: {service_name} tomorrow at {salon_name}',
       CONCAT(
         'Hi {client_name},\n\n',
         'Just a reminder — you have a {service_name} appointment coming up.\n\n',
         '  When:    {appointment_at}\n',
         '  Stylist: {stylist_name}\n',
         '  Salon:   {salon_name}\n',
         '  Fee:     {fee_amount}\n\n',
         '{disclaimer}\n\n',
         '{business_name}'
       )
FROM accounts a
WHERE a.product_type = 'hairdressers';
