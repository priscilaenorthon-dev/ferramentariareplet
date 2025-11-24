-- Tabelas MySQL para o backend PHP (compatível com XAMPP/MySQL 8).

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  username VARCHAR(191) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(191) NOT NULL,
  last_name VARCHAR(191) NOT NULL,
  email VARCHAR(191),
  matriculation VARCHAR(191) UNIQUE,
  department VARCHAR(191),
  role VARCHAR(32) NOT NULL DEFAULT 'user',
  qr_code VARCHAR(191) UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS tool_classes (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  name VARCHAR(191) NOT NULL,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS tool_models (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  name VARCHAR(191) NOT NULL,
  requires_calibration TINYINT(1) NOT NULL DEFAULT 0,
  calibration_interval_days INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS tools (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  name VARCHAR(191) NOT NULL,
  code VARCHAR(191) NOT NULL UNIQUE,
  class_id CHAR(36),
  model_id CHAR(36),
  quantity INT NOT NULL DEFAULT 1,
  available_quantity INT NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL DEFAULT 'available',
  last_calibration_date DATETIME NULL,
  next_calibration_date DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (class_id) REFERENCES tool_classes(id) ON DELETE SET NULL,
  FOREIGN KEY (model_id) REFERENCES tool_models(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS loans (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  batch_id CHAR(36),
  tool_id CHAR(36) NOT NULL,
  user_id CHAR(36),
  operator_id CHAR(36),
  quantity_loaned INT NOT NULL DEFAULT 1,
  loan_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expected_return_date DATETIME,
  return_date DATETIME,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  user_confirmation TINYINT(1) NOT NULL DEFAULT 0,
  user_confirmation_date DATETIME,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS calibration_alerts (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  tool_id CHAR(36) NOT NULL,
  alert_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_date DATETIME NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  acknowledged_by CHAR(36),
  acknowledged_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  user_id CHAR(36),
  target_type VARCHAR(32) NOT NULL,
  target_id CHAR(36) NOT NULL,
  action VARCHAR(32) NOT NULL,
  description TEXT NOT NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
