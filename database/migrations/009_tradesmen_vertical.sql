-- Add tradesmen to product_type enum
ALTER TABLE accounts
  MODIFY COLUMN product_type
    ENUM('landlords','dentists','agents','hairdressers','tradesmen')
    NOT NULL DEFAULT 'landlords';

-- Jobs: one row per booked job
CREATE TABLE IF NOT EXISTS jobs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id       INT UNSIGNED      NOT NULL,
    tradesman_id     INT UNSIGNED      NOT NULL  COMMENT 'FK → units.id',
    client_id        INT UNSIGNED      NOT NULL  COMMENT 'FK → tenants.id',
    job_type         VARCHAR(255)      NOT NULL,
    estimated_cost_cents INT UNSIGNED  NOT NULL DEFAULT 0,
    address          VARCHAR(500)      NULL,
    scheduled_at     DATETIME          NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    status           ENUM('scheduled','completed','canceled','no_show') NOT NULL DEFAULT 'scheduled',
    notes            TEXT              NULL,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_account_at (account_id, scheduled_at),
    CONSTRAINT fk_job_account    FOREIGN KEY (account_id)   REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_tradesman  FOREIGN KEY (tradesman_id) REFERENCES units(id)    ON DELETE CASCADE,
    CONSTRAINT fk_job_client     FOREIGN KEY (client_id)    REFERENCES tenants(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-account job reminder rule templates
CREATE TABLE IF NOT EXISTS job_reminder_rules (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    stage      ENUM('confirmation','reminder_24h','reminder_2h','completion') NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    body       TEXT         NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_rule (account_id, stage),
    CONSTRAINT fk_job_rules_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheduled and sent job reminders
CREATE TABLE IF NOT EXISTS job_reminders (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id     INT UNSIGNED NOT NULL,
    stage      ENUM('confirmation','reminder_24h','reminder_2h','completion') NOT NULL,
    channel    ENUM('email','sms') NOT NULL DEFAULT 'email',
    scheduled_at DATETIME   NOT NULL,
    sent_at    DATETIME     NULL,
    status     ENUM('pending','sent','failed','suppressed') NOT NULL DEFAULT 'pending',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_reminder (job_id, stage, channel),
    CONSTRAINT fk_job_reminders FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default reminder rules for any existing tradesmen accounts
INSERT IGNORE INTO job_reminder_rules (account_id, stage, subject, body, is_active)
SELECT a.id,
       'confirmation',
       'Your {job_type} appointment is confirmed',
       CONCAT(
         'Hi {client_name},\n\n',
         'Your job is confirmed!\n\n',
         '  Service:  {job_type}\n',
         '  When:     {scheduled_at}\n',
         '  Where:    {address}\n',
         '  Tradesman:{tradesman_name}\n',
         '  Estimate: {estimated_cost}\n\n',
         '{payment_link}\n',
         '{contact_phone}\n\n',
         'See you then!\n',
         '{business_name}'
       ),
       1
FROM accounts a WHERE a.product_type = 'tradesmen';

INSERT IGNORE INTO job_reminder_rules (account_id, stage, subject, body, is_active)
SELECT a.id,
       'reminder_24h',
       'Reminder: {job_type} tomorrow',
       CONCAT(
         'Hi {client_name},\n\n',
         'Just a reminder — you have a job scheduled for tomorrow.\n\n',
         '  Service:  {job_type}\n',
         '  When:     {scheduled_at}\n',
         '  Where:    {address}\n',
         '  Tradesman:{tradesman_name}\n',
         '  Estimate: {estimated_cost}\n\n',
         '{payment_link}\n',
         '{contact_phone}\n\n',
         '{business_name}'
       ),
       1
FROM accounts a WHERE a.product_type = 'tradesmen';

INSERT IGNORE INTO job_reminder_rules (account_id, stage, subject, body, is_active)
SELECT a.id,
       'reminder_2h',
       'Your {job_type} is in 2 hours',
       CONCAT(
         'Hi {client_name},\n\n',
         'Your {job_type} is coming up in about 2 hours.\n\n',
         '  When:  {scheduled_at}\n',
         '  Where: {address}\n\n',
         '{contact_phone}\n\n',
         '{business_name}'
       ),
       1
FROM accounts a WHERE a.product_type = 'tradesmen';

INSERT IGNORE INTO job_reminder_rules (account_id, stage, subject, body, is_active)
SELECT a.id,
       'completion',
       'Thanks for choosing {business_name}',
       CONCAT(
         'Hi {client_name},\n\n',
         'Thanks for having us out today! We hope everything looks great.\n\n',
         'If you have any questions or need follow-up work, don\'t hesitate to reach out.\n\n',
         '{payment_link}\n',
         '{contact_phone}\n\n',
         '{business_name}'
       ),
       0
FROM accounts a WHERE a.product_type = 'tradesmen';
