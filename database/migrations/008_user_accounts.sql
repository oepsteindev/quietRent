-- Allow one user to belong to multiple accounts (businesses)
CREATE TABLE IF NOT EXISTS user_accounts (
    user_id    INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    role       ENUM('owner','member') NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, account_id),
    CONSTRAINT fk_ua_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ua_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

-- Seed all existing users into the junction table for their primary account
INSERT IGNORE INTO user_accounts (user_id, account_id, role)
SELECT id, account_id, 'owner' FROM users;
