USE senaeats;
ALTER TABLE restaurants
  ADD COLUMN phone VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN hours VARCHAR(80) NOT NULL DEFAULT '',
  ADD COLUMN description VARCHAR(500) NOT NULL DEFAULT '';

CREATE TABLE gallery_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT UNSIGNED NOT NULL,
  filename VARCHAR(60) NOT NULL,
  caption VARCHAR(120) NOT NULL DEFAULT '',
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  INDEX idx_rest (restaurant_id)
) ENGINE=InnoDB;

CREATE TABLE courier_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  vehicle VARCHAR(60) NOT NULL DEFAULT '',
  bio VARCHAR(300) NOT NULL DEFAULT '',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorites (
  user_id INT UNSIGNED NOT NULL,
  target_type ENUM('restaurant','courier') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  restaurant_id INT UNSIGNED NOT NULL,
  courier_id INT UNSIGNED NULL,
  restaurant_rating TINYINT UNSIGNED NOT NULL,
  courier_rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(500) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES users(id),
  INDEX idx_rest (restaurant_id), INDEX idx_courier (courier_id)
) ENGINE=InnoDB;

-- Payouts: balance = released escrow shares minus withdrawals (checked under a row lock).
CREATE TABLE withdrawals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  status ENUM('paid') NOT NULL DEFAULT 'paid',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;
