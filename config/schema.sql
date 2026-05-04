

CREATE DATABASE IF NOT EXISTS cortexpos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cortexpos;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','cashier') NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED DEFAULT NULL,
    last_login    DATETIME DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    phone           VARCHAR(30) DEFAULT NULL,
    points          INT UNSIGNED NOT NULL DEFAULT 0,
    rank_id         INT UNSIGNED DEFAULT NULL,
    streak_days     INT UNSIGNED NOT NULL DEFAULT 0,
    streak_restores INT UNSIGNED NOT NULL DEFAULT 5,
    last_streak     DATE DEFAULT NULL,
    profile_public  TINYINT(1) NOT NULL DEFAULT 1,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login      DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otc_codes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_hash     VARCHAR(255) NOT NULL,
    code_prefix   CHAR(4) NOT NULL,
    role          ENUM('admin','cashier') NOT NULL,
    generated_by  INT UNSIGNED NOT NULL,
    note          VARCHAR(255) DEFAULT NULL,
    expires_at    DATETIME NOT NULL,
    used_at       DATETIME DEFAULT NULL,
    used_by_email VARCHAR(191) DEFAULT NULL,
    is_used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prefix (code_prefix),
    INDEX idx_used (is_used)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(50) NOT NULL,
    role       VARCHAR(20) DEFAULT NULL,
    identifier VARCHAR(191) DEFAULT NULL,
    user_id    INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    detail     VARCHAR(512) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ranks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(60) NOT NULL,
    min_points      INT UNSIGNED NOT NULL DEFAULT 0,
    color           VARCHAR(7) DEFAULT '#c07a2f',
    icon            VARCHAR(10) DEFAULT '☕',
    streak_restores INT UNSIGNED NOT NULL DEFAULT 5,
    can_preorder    TINYINT(1) NOT NULL DEFAULT 0,
    description     TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cafe_tables (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number      INT UNSIGNED NOT NULL UNIQUE,
    capacity    INT UNSIGNED NOT NULL DEFAULT 4,
    status      ENUM('free','occupied','reserved','cleaning') NOT NULL DEFAULT 'free',
    client_id   INT UNSIGNED DEFAULT NULL,
    zone        VARCHAR(60) DEFAULT 'Main',
    notes       VARCHAR(255) DEFAULT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80) NOT NULL,
    icon       VARCHAR(10) DEFAULT '☕',
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingredients (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    unit          VARCHAR(20) NOT NULL DEFAULT 'g',
    stock_qty     DECIMAL(10,2) NOT NULL DEFAULT 0,
    alert_qty     DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost_per_unit DECIMAL(8,4) NOT NULL DEFAULT 0,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED NOT NULL,
    name         VARCHAR(120) NOT NULL,
    description  TEXT DEFAULT NULL,
    price        DECIMAL(8,2) NOT NULL,
    points_earn  INT UNSIGNED NOT NULL DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    is_featured  TINYINT(1) NOT NULL DEFAULT 0,
    sort_order   INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_ingredients (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id    INT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    quantity      DECIMAL(10,3) NOT NULL,
    UNIQUE KEY uq (product_id, ingredient_id),
    FOREIGN KEY (product_id)    REFERENCES products(id)     ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ref       VARCHAR(20) NOT NULL UNIQUE,
    client_id       INT UNSIGNED DEFAULT NULL,
    table_id        INT UNSIGNED DEFAULT NULL,
    cashier_id      INT UNSIGNED DEFAULT NULL,
    status          ENUM('pending','confirmed','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
    type            ENUM('dine_in','takeaway','preorder') NOT NULL DEFAULT 'dine_in',
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0,
    points_earned   INT UNSIGNED NOT NULL DEFAULT 0,
    notes           TEXT DEFAULT NULL,
    cancelled_reason VARCHAR(255) DEFAULT NULL,
    receipt_sent    TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client  (client_id),
    INDEX idx_cashier (cashier_id),
    INDEX idx_status  (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(8,2) NOT NULL,
    subtotal   DECIMAL(10,2) NOT NULL,
    notes      VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shifts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cashier_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at   DATETIME DEFAULT NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coffee_moments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id     INT UNSIGNED NOT NULL,
    image_path    VARCHAR(255) NOT NULL,
    caption       VARCHAR(255) DEFAULT NULL,
    points_earned INT UNSIGNED NOT NULL DEFAULT 0,
    is_approved   TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_tickets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id  INT UNSIGNED NOT NULL,
    category   ENUM('lost_item','order_issue','reward_problem','other') NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    status     ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    sender_role ENUM('client','admin','cashier') NOT NULL,
    sender_id   INT UNSIGNED NOT NULL,
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_role ENUM('admin','cashier','client','all') NOT NULL DEFAULT 'all',
    title       VARCHAR(180) NOT NULL,
    body        TEXT DEFAULT NULL,
    type        VARCHAR(40) DEFAULT 'info',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(191) NOT NULL,
    role       ENUM('admin','cashier','client') NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
