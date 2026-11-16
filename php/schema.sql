CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(16) NOT NULL DEFAULT '📦',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_orders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(120) NOT NULL,
  unit_price DECIMAL(10, 2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10, 2) NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  INDEX idx_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
  ('Store Admin', 'admin@example.com', '$2y$10$cF.4BB23mccBBcxS1EIM0eq9ru5nFWlWfbDlBy7zjn8ZGv2dOXT9a', 'admin'),
  ('Demo Customer', 'customer@example.com', '$2y$10$gOmZv9ZirEdlwpBQJap0.OLbSp2x5v1ugXRQldhs3pJSMnHVluHFa', 'customer');

INSERT INTO products (name, description, price, stock, image)
SELECT seed_products.name, seed_products.description, seed_products.price, seed_products.stock, seed_products.image
FROM (
  SELECT 'Espresso Beans 1kg' AS name, 'Freshly roasted arabica beans with notes of cocoa and caramel.' AS description, 24.50 AS price, 40 AS stock, '☕' AS image
  UNION ALL SELECT 'Ceramic Coffee Mug', 'Hand-glazed 350ml mug that keeps your drink warm.', 12.00, 75, '🍵'
  UNION ALL SELECT 'Pour-Over Kettle', 'Gooseneck kettle with precision temperature control.', 49.90, 18, '🫖'
  UNION ALL SELECT 'Manual Grinder', 'Stainless steel burr grinder for consistent grounds.', 39.00, 22, '⚙️'
  UNION ALL SELECT 'Paper Filters (100)', 'Unbleached cone filters for pour-over brewing.', 6.50, 120, '📄'
  UNION ALL SELECT 'Cold Brew Bottle', '1L glass bottle with a fine mesh filter.', 27.00, 30, '🧊'
  UNION ALL SELECT 'Barista Scale', 'Compact digital scale with built-in timer.', 32.50, 15, '⚖️'
  UNION ALL SELECT 'Reusable Cup', 'Double-wall tumbler that keeps drinks hot for hours.', 18.00, 50, '🥤'
  UNION ALL SELECT 'Vanilla Syrup 250ml', 'Classic vanilla syrup for lattes, iced coffee, and desserts.', 8.75, 60, '🍯'
  UNION ALL SELECT 'Chocolate Drizzle Sauce', 'Rich chocolate sauce perfect for mochas and toppings.', 7.25, 55, '🍫'
  UNION ALL SELECT 'Matcha Powder 100g', 'Ceremonial-grade matcha with a smooth, vibrant flavor.', 21.00, 28, '🍃'
  UNION ALL SELECT 'Iced Coffee Tumbler', 'Insulated 500ml tumbler designed for cold drinks on the go.', 16.50, 45, '🧋'
  UNION ALL SELECT 'Bamboo Stir Sticks (50)', 'Eco-friendly stir sticks for home or office coffee stations.', 4.99, 150, '🎋'
  UNION ALL SELECT 'French Press 600ml', 'Classic glass French press for rich, full-bodied coffee.', 29.95, 20, '🫙'
  UNION ALL SELECT 'Cinnamon Spice Blend', 'Warm cinnamon blend to sprinkle over cappuccinos and pastries.', 5.50, 70, '🌿'
  UNION ALL SELECT 'Travel Mug Sleeve', 'Neoprene sleeve for extra grip and heat protection.', 9.99, 65, '🧤'
  UNION ALL SELECT 'Caramel Syrup 250ml', 'Buttery caramel syrup for sweet coffee creations.', 8.75, 58, '🍮'
  UNION ALL SELECT 'Milk Frother Wand', 'Handheld electric frother for creamy foam in seconds.', 14.25, 35, '🪄'
) AS seed_products
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE products.name = seed_products.name
);
