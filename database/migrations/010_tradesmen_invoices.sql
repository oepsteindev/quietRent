-- Invoice header
CREATE TABLE IF NOT EXISTS invoices (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id     INT UNSIGNED     NOT NULL,
    client_id      INT UNSIGNED     NOT NULL  COMMENT 'FK → tenants.id',
    job_id         INT UNSIGNED     NULL      COMMENT 'FK → jobs.id (optional)',
    invoice_number VARCHAR(32)      NOT NULL,
    status         ENUM('draft','sent','paid') NOT NULL DEFAULT 'draft',
    due_date       DATE             NULL,
    notes          TEXT             NULL,
    sent_at        DATETIME         NULL,
    paid_at        DATETIME         NULL,
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_account (account_id),
    INDEX idx_invoice_client  (client_id),
    CONSTRAINT fk_invoice_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_client  FOREIGN KEY (client_id)  REFERENCES tenants(id)  ON DELETE CASCADE,
    CONSTRAINT fk_invoice_job     FOREIGN KEY (job_id)     REFERENCES jobs(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice line items
CREATE TABLE IF NOT EXISTS invoice_line_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id     INT UNSIGNED     NOT NULL,
    description    VARCHAR(500)     NOT NULL,
    quantity       DECIMAL(10,2)    NOT NULL DEFAULT 1.00,
    unit_price_cents INT UNSIGNED   NOT NULL DEFAULT 0,
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_line_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
