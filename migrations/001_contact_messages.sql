-- Stores submissions from public/contact.php.
-- Run once against the ethicalbuy database:
--   mysql -u root -p ethicalbuy < migrations/001_contact_messages.sql

CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(255)  NOT NULL,
    message    TEXT          NOT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The app's database user needs INSERT here:
--   GRANT INSERT ON ethicalbuy.contact_messages TO 'ethicalbuy'@'localhost';
