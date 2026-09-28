-- Library Management System - Database Schema
-- Engine: InnoDB, utf8mb4 (supports emoji/special characters), foreign keys ON

-- ============================================
-- IDENTITY
-- ============================================
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patron_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,      -- Undergraduate, Graduate, Faculty, Staff
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  id_number VARCHAR(30) NOT NULL UNIQUE,   -- student/employee number, used to log in
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150),
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patron_profiles (
  user_id INT PRIMARY KEY,
  patron_category_id INT NOT NULL,
  program VARCHAR(100),         -- course/department
  year_level VARCHAR(20),
  contact_number VARCHAR(30),
  address VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (patron_category_id) REFERENCES patron_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CATALOG  (bib_record = the work, edition = ISBN version, item = physical copy)
-- ============================================
CREATE TABLE material_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,      -- Book, Thesis, Periodical, AV Material
  is_circulating TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE       -- General Circulation, Reference, Course Reserve...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,            -- e.g. "2nd Floor - Shelf A3"
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bib_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255),
  summary TEXT,
  cover_image VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bib_authors (
  bib_record_id INT NOT NULL,
  author_id INT NOT NULL,
  author_role VARCHAR(30) DEFAULT 'author',   -- author, editor, illustrator
  PRIMARY KEY (bib_record_id, author_id),
  FOREIGN KEY (bib_record_id) REFERENCES bib_records(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bib_subjects (
  bib_record_id INT NOT NULL,
  subject_id INT NOT NULL,
  PRIMARY KEY (bib_record_id, subject_id),
  FOREIGN KEY (bib_record_id) REFERENCES bib_records(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE editions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bib_record_id INT NOT NULL,
  isbn VARCHAR(20),
  publisher VARCHAR(150),
  publication_year YEAR,
  edition_statement VARCHAR(100),
  format VARCHAR(50),                 -- Hardcover, Paperback, E-book...
  FOREIGN KEY (bib_record_id) REFERENCES bib_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  edition_id INT NOT NULL,
  material_type_id INT NOT NULL,
  collection_id INT NOT NULL,
  location_id INT,
  barcode VARCHAR(50) NOT NULL UNIQUE,
  accession_number VARCHAR(50) UNIQUE,
  status ENUM('available','checked_out','on_hold_shelf','lost','withdrawn','in_repair') NOT NULL DEFAULT 'available',
  item_condition ENUM('new','good','fair','poor') NOT NULL DEFAULT 'good',
  date_acquired DATE,
  price DECIMAL(10,2),
  FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
  FOREIGN KEY (material_type_id) REFERENCES material_types(id),
  FOREIGN KEY (collection_id) REFERENCES collections(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CIRCULATION
-- ============================================
CREATE TABLE circulation_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patron_category_id INT NOT NULL,
  material_type_id INT NOT NULL,
  loan_period_days INT NOT NULL DEFAULT 7,
  max_renewals INT NOT NULL DEFAULT 1,
  max_concurrent_loans INT NOT NULL DEFAULT 3,
  fine_rate_per_day DECIMAL(6,2) NOT NULL DEFAULT 5.00,
  grace_period_days INT NOT NULL DEFAULT 0,
  is_holdable TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_policy (patron_category_id, material_type_id),
  FOREIGN KEY (patron_category_id) REFERENCES patron_categories(id),
  FOREIGN KEY (material_type_id) REFERENCES material_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_closures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  closure_date DATE NOT NULL UNIQUE,
  description VARCHAR(150)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  user_id INT NOT NULL,
  checked_out_by INT NOT NULL,
  checked_out_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at DATETIME NOT NULL,
  returned_at DATETIME,
  returned_to INT,
  renewal_count INT NOT NULL DEFAULT 0,
  status ENUM('active','returned','overdue','lost') NOT NULL DEFAULT 'active',
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (checked_out_by) REFERENCES users(id),
  FOREIGN KEY (returned_to) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- HOLDS / RESERVATIONS
-- ============================================
CREATE TABLE holds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bib_record_id INT NOT NULL,
  user_id INT NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','ready','fulfilled','cancelled','expired') NOT NULL DEFAULT 'pending',
  notified_at DATETIME,
  expires_at DATETIME,
  queue_position INT,
  FOREIGN KEY (bib_record_id) REFERENCES bib_records(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FINES  (append-only ledger, never store just a "balance" column)
-- ============================================
CREATE TABLE account_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  loan_id INT,
  type ENUM('charge','payment','waiver','adjustment','refund') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  created_by INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (loan_id) REFERENCES loans(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVENTORY
-- ============================================
CREATE TABLE item_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  old_status VARCHAR(30),
  new_status VARCHAR(30) NOT NULL,
  changed_by INT,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(255),
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stocktake_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  started_by INT NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME,
  location_id INT,
  status ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  FOREIGN KEY (started_by) REFERENCES users(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stocktake_scans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  item_id INT NOT NULL,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  was_expected TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (session_id) REFERENCES stocktake_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SYSTEM
-- ============================================
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DIGITAL LIBRARY  (downloadable files attached to a title; not a
-- physical copy - no barcode, no checkout/return, no due date)
-- ============================================
CREATE TABLE digital_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bib_record_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,       -- original filename, shown to patrons
  stored_path VARCHAR(255) NOT NULL,    -- path under storage/digital_files/, not web-accessible directly
  file_format VARCHAR(20) NOT NULL,     -- PDF, EPUB, etc.
  file_size_bytes BIGINT NOT NULL,
  download_count INT NOT NULL DEFAULT 0,
  uploaded_by INT NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bib_record_id) REFERENCES bib_records(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
