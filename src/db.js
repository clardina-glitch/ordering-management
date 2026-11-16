const mysql = require('mysql2/promise');

function buildSsl() {
  if (String(process.env.DB_SSL || '').toLowerCase() !== 'true') return undefined;

  const ca = process.env.DB_SSL_CA;
  if (ca) {
    return { rejectUnauthorized: true, ca: ca.replace(/\\n/g, '\n') };
  }

  return { rejectUnauthorized: false };
}

const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'ordering_system',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  charset: 'utf8mb4',
  ssl: buildSsl()
});

module.exports = pool;
