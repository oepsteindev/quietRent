SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- --------------------------------------------------------
-- accounts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255)        NOT NULL,
    plan          ENUM('trial','starter','pro') NOT NULL DEFAULT 'trial',
    trial_ends_at DATETIME            NULL,
    stripe_customer_id VARCHAR(255)   NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    subscription_status ENUM('active','past_due','canceled','trialing') NOT NULL DEFAULT 'trialing',
    subscription_ends_at DATETIME     NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id    INT UNSIGNED        NOT NULL,
    name          VARCHAR(255)        NOT NULL,
    email         VARCHAR(255)        NOT NULL,
    password_hash VARCHAR(255)        NOT NULL,
    role          ENUM('landlord','manager') NOT NULL DEFAULT 'landlord',
    reset_token   VARCHAR(64)         NULL,
    reset_expires DATETIME            NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT fk_users_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- properties
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS properties (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id    INT UNSIGNED        NOT NULL,
    name          VARCHAR(255)        NOT NULL,
    address_line1 VARCHAR(255)        NOT NULL,
    address_line2 VARCHAR(255)        NULL,
    city          VARCHAR(100)        NOT NULL,
    state         VARCHAR(100)        NOT NULL,
    zip           VARCHAR(20)         NOT NULL,
    is_active     TINYINT(1)          NOT NULL DEFAULT 1,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_properties_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- units
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id       INT UNSIGNED     NOT NULL,
    unit_label        VARCHAR(50)      NOT NULL,
    monthly_rent_cents INT UNSIGNED    NOT NULL,
    due_day           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1-28',
    grace_days        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    late_fee_type     ENUM('none','flat','percent') NOT NULL DEFAULT 'none',
    late_fee_value    DECIMAL(10,2)    NOT NULL DEFAULT 0,
    late_fee_max_cents INT UNSIGNED    NULL COMMENT 'optional cap',
    is_active         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_units_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- tenants
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id        INT UNSIGNED     NOT NULL,
    unit_id           INT UNSIGNED     NOT NULL,
    full_name         VARCHAR(255)     NOT NULL,
    email             VARCHAR(255)     NOT NULL,
    phone             VARCHAR(30)      NULL,
    preferred_channel ENUM('email','sms','both') NOT NULL DEFAULT 'email',
    reminders_paused  TINYINT(1)       NOT NULL DEFAULT 0,
    is_active         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tenants_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenants_unit   FOREIGN KEY (unit_id)    REFERENCES units(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- leases
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS leases (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED        NOT NULL,
    unit_id       INT UNSIGNED        NOT NULL,
    start_date    DATE                NOT NULL,
    end_date      DATE                NULL,
    status        ENUM('active','ended','moved_out') NOT NULL DEFAULT 'active',
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leases_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_leases_unit   FOREIGN KEY (unit_id)   REFERENCES units(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- rent_charges  (one row per tenant per month)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS rent_charges (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id         INT UNSIGNED     NOT NULL,
    tenant_id       INT UNSIGNED     NOT NULL,
    period_month    CHAR(7)          NOT NULL COMMENT 'YYYY-MM',
    amount_cents    INT UNSIGNED     NOT NULL,
    due_date        DATE             NOT NULL,
    late_fee_cents  INT UNSIGNED     NOT NULL DEFAULT 0,
    status          ENUM('upcoming','due','late','paid','waived') NOT NULL DEFAULT 'upcoming',
    paid_at         DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_charge_period (unit_id, tenant_id, period_month),
    CONSTRAINT fk_charges_unit   FOREIGN KEY (unit_id)   REFERENCES units(id)   ON DELETE CASCADE,
    CONSTRAINT fk_charges_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- reminder_rules  (per-account defaults, editable)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS reminder_rules (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id    INT UNSIGNED        NOT NULL,
    stage         ENUM('pre_due','due_day','late_1','late_5') NOT NULL,
    day_offset    TINYINT             NOT NULL COMMENT 'negative=before, positive=after due date',
    subject       VARCHAR(255)        NOT NULL,
    body          TEXT                NOT NULL,
    is_active     TINYINT(1)          NOT NULL DEFAULT 1,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rule_stage (account_id, stage),
    CONSTRAINT fk_rules_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- reminders  (scheduled and sent)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS reminders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rent_charge_id  INT UNSIGNED     NOT NULL,
    stage           ENUM('pre_due','due_day','late_1','late_5') NOT NULL,
    channel         ENUM('email','sms') NOT NULL DEFAULT 'email',
    scheduled_at    DATETIME         NOT NULL,
    sent_at         DATETIME         NULL,
    status          ENUM('pending','sent','failed','suppressed') NOT NULL DEFAULT 'pending',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reminder_stage_channel (rent_charge_id, stage, channel),
    CONSTRAINT fk_reminders_charge FOREIGN KEY (rent_charge_id) REFERENCES rent_charges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- imports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS imports (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id    INT UNSIGNED        NOT NULL,
    filename      VARCHAR(255)        NOT NULL,
    row_count     INT UNSIGNED        NOT NULL DEFAULT 0,
    status        ENUM('pending','complete','failed') NOT NULL DEFAULT 'pending',
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_imports_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- import_rows
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_rows (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id   INT UNSIGNED     NOT NULL,
    row_index   INT UNSIGNED     NOT NULL,
    raw_data    JSON             NOT NULL,
    status      ENUM('ok','error','skipped') NOT NULL DEFAULT 'ok',
    error_msg   VARCHAR(500)     NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_rows_import FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- audit_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id  INT UNSIGNED     NOT NULL,
    user_id     INT UNSIGNED     NULL,
    event       VARCHAR(100)     NOT NULL,
    entity_type VARCHAR(50)      NULL,
    entity_id   INT UNSIGNED     NULL,
    meta        JSON             NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Default reminder rule templates inserted on account signup
-- (handled in PHP, not seeded here)
-- --------------------------------------------------------
