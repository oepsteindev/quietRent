-- Add product_type if not already present (idempotent; 001 may not have run)
ALTER TABLE accounts
  ADD COLUMN IF NOT EXISTS product_type
    ENUM('landlords','dentists','agents','hairdressers')
    NOT NULL DEFAULT 'landlords'
    AFTER name;

-- Extend enum if column was already there without hairdressers
ALTER TABLE accounts
  MODIFY COLUMN product_type
    ENUM('landlords','dentists','agents','hairdressers')
    NOT NULL DEFAULT 'landlords';

-- Appointments: one row per booked appointment
CREATE TABLE IF NOT EXISTS appointments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id       INT UNSIGNED      NOT NULL,
    stylist_id       INT UNSIGNED      NOT NULL  COMMENT 'FK → units.id (chair/station)',
    client_id        INT UNSIGNED      NOT NULL  COMMENT 'FK → tenants.id',
    service_name     VARCHAR(255)      NOT NULL,
    fee_cents        INT UNSIGNED      NOT NULL DEFAULT 0,
    appointment_at   DATETIME          NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    status           ENUM('scheduled','completed','canceled','no_show') NOT NULL DEFAULT 'scheduled',
    notes            TEXT              NULL,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_appt_account_at (account_id, appointment_at),
    CONSTRAINT fk_appt_account  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_stylist  FOREIGN KEY (stylist_id) REFERENCES units(id)    ON DELETE CASCADE,
    CONSTRAINT fk_appt_client   FOREIGN KEY (client_id)  REFERENCES tenants(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-account appointment reminder rule templates
CREATE TABLE IF NOT EXISTS appointment_reminder_rules (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    stage      ENUM('reminder_48h','reminder_2h') NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    body       TEXT         NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appt_rule (account_id, stage),
    CONSTRAINT fk_appt_rules_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheduled and sent appointment reminders
CREATE TABLE IF NOT EXISTS appointment_reminders (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    stage          ENUM('reminder_48h','reminder_2h') NOT NULL,
    channel        ENUM('email','sms') NOT NULL DEFAULT 'email',
    scheduled_at   DATETIME     NOT NULL,
    sent_at        DATETIME     NULL,
    status         ENUM('pending','sent','failed','suppressed') NOT NULL DEFAULT 'pending',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appt_reminder (appointment_id, stage, channel),
    CONSTRAINT fk_appt_reminders FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
