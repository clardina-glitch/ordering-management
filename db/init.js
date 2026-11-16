require('dotenv').config();

const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');

const DB_NAME = process.env.DB_NAME || 'ordering_system';

function buildSsl() {
  if (String(process.env.DB_SSL || '').toLowerCase() !== 'true') return undefined;

  const ca = process.env.DB_SSL_CA;
  if (ca) {
    return { rejectUnauthorized: true, ca: ca.replace(/\\n/g, '\n') };
  }

  return { rejectUnauthorized: false };
}

const products = [
  ['Espresso Beans 1kg', 'Freshly roasted arabica beans with notes of cocoa and caramel.', 24.5, 40, '☕'],
  ['Ceramic Coffee Mug', 'Hand-glazed 350ml mug that keeps your drink warm.', 12.0, 75, '🍵'],
  ['Pour-Over Kettle', 'Gooseneck kettle with precision temperature control.', 49.9, 18, '🫖'],
  ['Manual Grinder', 'Stainless steel burr grinder for consistent grounds.', 39.0, 22, '⚙️'],
  ['Paper Filters (100)', 'Unbleached cone filters for pour-over brewing.', 6.5, 120, '📄'],
  ['Cold Brew Bottle', '1L glass bottle with a fine mesh filter.', 27.0, 30, '🧊'],
  ['Barista Scale', 'Compact digital scale with built-in timer.', 32.5, 15, '⚖️'],
  ['Reusable Cup', 'Double-wall tumbler that keeps drinks hot for hours.', 18.0, 50, '🥤'],
  ['Vanilla Syrup 250ml', 'Classic vanilla syrup for lattes, iced coffee, and desserts.', 8.75, 60, '🍯'],
  ['Chocolate Drizzle Sauce', 'Rich chocolate sauce perfect for mochas and toppings.', 7.25, 55, '🍫'],
  ['Matcha Powder 100g', 'Ceremonial-grade matcha with a smooth, vibrant flavor.', 21.0, 28, '🍃'],
  ['Iced Coffee Tumbler', 'Insulated 500ml tumbler designed for cold drinks on the go.', 16.5, 45, '🧋'],
  ['Bamboo Stir Sticks (50)', 'Eco-friendly stir sticks for home or office coffee stations.', 4.99, 150, '🎋'],
  ['French Press 600ml', 'Classic glass French press for rich, full-bodied coffee.', 29.95, 20, '🫙'],
  ['Cinnamon Spice Blend', 'Warm cinnamon blend to sprinkle over cappuccinos and pastries.', 5.5, 70, '🌿'],
  ['Travel Mug Sleeve', 'Neoprene sleeve for extra grip and heat protection.', 9.99, 65, '🧤'],
  ['Caramel Syrup 250ml', 'Buttery caramel syrup for sweet coffee creations.', 8.75, 58, '🍮'],
  ['Milk Frother Wand', 'Handheld electric frother for creamy foam in seconds.', 14.25, 35, '🪄']
];

async function main() {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    ssl: buildSsl(),
    multipleStatements: false
  });

  try {
    try {
      await connection.query(
        `CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
      );
    } catch (error) {
      if (!['ER_DBACCESS_DENIED_ERROR', 'ER_ACCESS_DENIED_ERROR'].includes(error.code)) {
        throw error;
      }
      console.warn(`Could not create database "${DB_NAME}"; using the existing database.`);
    }

    await connection.query(`USE \`${DB_NAME}\``);

    const schema = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
    const statements = schema
      .split(';')
      .map((sql) => sql.trim())
      .filter(Boolean);

    for (const statement of statements) {
      await connection.query(statement);
    }

    const adminHash = await bcrypt.hash('admin123', 10);
    const customerHash = await bcrypt.hash('customer123', 10);

    await connection.query(
      `INSERT IGNORE INTO users (name, email, password_hash, role) VALUES ?`,
      [[
        ['Store Admin', 'admin@example.com', adminHash, 'admin'],
        ['Demo Customer', 'customer@example.com', customerHash, 'customer']
      ]]
    );

    const [existingRows] = await connection.query('SELECT name FROM products');
    const existingNames = new Set(existingRows.map((row) => row.name));
    const missingProducts = products.filter(([name]) => !existingNames.has(name));

    if (missingProducts.length > 0) {
      await connection.query(
        'INSERT INTO products (name, description, price, stock, image) VALUES ?',
        [missingProducts]
      );
      console.log(`Inserted ${missingProducts.length} new product(s).`);
    } else {
      console.log('Products are already up to date.');
    }

    console.log(`Database "${DB_NAME}" is ready.`);
    console.log('Demo accounts:');
    console.log('  Admin    -> admin@example.com / admin123');
    console.log('  Customer -> customer@example.com / customer123');
  } finally {
    await connection.end();
  }
}

main().catch((error) => {
  console.error('Database initialization failed:', error.message);
  process.exit(1);
});
